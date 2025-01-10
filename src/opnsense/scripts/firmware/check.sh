#!/bin/sh

# Copyright (C) 2015-2024 Franco Fichtner <franco@opnsense.org>
# Copyright (C) 2014 Deciso B.V.
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

# This script generates a json structured file with the following content:
#
# connection: error|unauthenticated|misconfigured|unresolved|ok
# repository: error|untrusted|unsigned|revoked|incomplete|forbidden|ok
# last_check: <date_time_stamp>
# download_size: <size_of_total_downloads>[,<size_of_total_downloads>]
# new_packages: array with { name: <package_name>, version: <package_version> }
# reinstall_packages: array with { name: <package_name>, version: <package_version> }
# remove_packages: array with { name: <package_name>, version: <package_version> }
# downgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

# clear the file before we may wait for other init glue below
JSONFILE="/tmp/pkg_upgrade.json"
rm -f ${JSONFILE}

REQUEST="CHECK FOR UPDATES"

. /usr/local/opnsense/scripts/firmware/config.sh

LICENSEFILE="/usr/local/opnsense/version/core.license"
OUTFILE="/tmp/pkg_update.out"

CUSTOMPKG=${1}

base_to_reboot=
connection="error"
download_size=
force_all=
itemcount=0
kernel_to_reboot=
last_check="unknown"
linecount=0
needs_reboot="0"
packages_downgraded=
packages_new=
packages_upgraded=
product_repo="OPNsense"
repository="error"
sets_upgraded=
upgrade_needs_reboot="0"

product_reboot=$(/usr/local/sbin/pluginctl -g system.firmware.reboot)
if [ -n "${product_reboot}" ]; then
	needs_reboot="1"
fi

product_suffix="-$(/usr/local/sbin/pluginctl -g system.firmware.type)"
if [ "${product_suffix}" = "-" ]; then
    product_suffix=
fi

last_check=$(date)
os_version=$(uname -sr)
product_id=$(opnsense-version -n)
product_target=opnsense${product_suffix}
product_version=$(opnsense-version -v)
product_abi=$(opnsense-version -a)
product_xabi=$(opnsense-version -x)

if [ -n "${product_xabi}" -a "${product_abi}" != "${product_xabi}" ]; then
    force_all="-f"
fi

# business subscriptions come with additional license metadata
if [ -n "$(opnsense-update -x)" ]; then
    output_txt -n "Fetching subscription information, please wait... "
    if output_cmd fetch -qT 30 -o "${LICENSEFILE}" "$(opnsense-update -M)/subscription"; then
        output_txt "done"
    fi
else
    rm -f ${LICENSEFILE}
fi

output_txt -n "Fetching changelog information, please wait... "
if output_cmd ${BASEDIR}/changelog.sh fetch; then
    output_txt "done"
fi

: > ${OUTFILE}
output_cmd -o ${OUTFILE} ${PKG} update -f

# always update the package manager so we can see the real updates directly
output_cmd ${PKG} upgrade -r "${product_repo}" -Uy pkg

# parse early errors
if grep -q 'No address record' ${OUTFILE}; then
    # DNS resolution failed
    connection="unresolved"
elif grep -q 'Cannot parse configuration' ${OUTFILE}; then
    # configuration error
    connection="misconfigured"
elif grep -q 'Authentication error' ${OUTFILE}; then
    # TLS or authentication error
    connection="unauthenticated"
elif grep -q 'No trusted public keys found' ${OUTFILE}; then
    # fingerprint mismatch
    repository="untrusted"
    connection="ok"
elif grep -q 'At least one of the certificates has been revoked' ${OUTFILE}; then
    # fingerprint mismatch
    repository="revoked"
    connection="ok"
elif grep -q 'No signature found' ${OUTFILE}; then
    # fingerprint not found
    repository="unsigned"
    connection="ok"
elif grep -q 'Forbidden' ${OUTFILE}; then
    # access not granted
    repository="forbidden"
    connection="ok"
elif grep -q 'Unable to update repository' ${OUTFILE}; then
    # repository not found
    connection="ok"
else
    # connection is still ok
    connection="ok"

    : > ${OUTFILE}

    # now check what happens when we would go ahead
    output_cmd -o ${OUTFILE} ${PKG} upgrade ${force_all} -Un
    if  [ -n "${CUSTOMPKG}" ]; then
        output_cmd -o ${OUTFILE} ${PKG} install -Un "${CUSTOMPKG}"
    elif [ "${product_id}" != "${product_target}" ]; then
        output_cmd -o ${OUTFILE} ${PKG} install -r "${product_repo}" -Un "${product_target}"
    elif [ -z "$(${PKG} rquery %n ${product_id})" ]; then
        # although this should say "to update matching" we emulate for
        # check below as the package manager does not catch this
        output_txt -o ${OUTFILE} "self: No packages available to install matching '${product_id}'"
    fi

    # Check for additional repository errors
    if grep -q 'Unable to update repository' ${OUTFILE}; then
        repository="error" # already set but reset here for clarity
    elif [ -n "${CUSTOMPKG}" ] && grep -q "No packages available to install matching..${CUSTOMPKG}" ${OUTFILE}; then
        repository="incomplete"
    elif grep -q "No packages available to install matching..${product_target}" ${OUTFILE}; then
        repository="incomplete"
    else
        # Repository can be used for updates
        repository="ok"

        MODE=

        while read LINE; do
            REPO=$(echo "${LINE}" | grep -o '\[.*\]' | tr -d '[]')
            if [ -z "${REPO}" ]; then
                REPO=${product_repo}
            fi

            for i in $(echo "${LINE}" | tr '[' '(' | cut -d '(' -f1); do
                case ${MODE} in
                DOWNGRADED:)
                    if [ "$(expr $linecount + 4)" -eq "$itemcount" ]; then
                        if [ "${i%:*}" = "${i}" ]; then
                            itemcount=0 # This is not a valid item so reset item count
                            MODE=
                        else
                            i=$(echo $i | tr -d :)
                            if [ -n "$packages_downgraded" ]; then
                                packages_downgraded=$packages_downgraded","
                            fi
                            packages_downgraded=$packages_downgraded"{\"name\":\"$i\",\"repository\":\"${REPO}\","
                        fi
                    fi
                    if [ "$(expr $linecount + 3)" -eq "$itemcount" ]; then
                        packages_downgraded=$packages_downgraded"\"current_version\":\"$i\","
                    fi
                    if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                        packages_downgraded=$packages_downgraded"\"new_version\":\"$i\"}"
                        itemcount=$(expr $itemcount + 4) # get ready for next item
                    fi
                    ;;
                INSTALLED:)
                    if [ "$(expr $linecount + 2)" -eq "$itemcount" ]; then
                        if [ "${i%:*}" = "${i}" ]; then
                            itemcount=0 # This is not a valid item so reset item count
                            MODE=
                        else
                            i=$(echo $i | tr -d :)
                            if [ -n "$packages_new" ]; then
                                packages_new=$packages_new","
                            fi
                            packages_new=$packages_new"{\"name\":\"$i\",\"repository\":\"${REPO}\","
                        fi
                    fi
                    if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                        packages_new=$packages_new"\"version\":\"$i\"}"
                        itemcount=$(expr $itemcount + 2) # get ready for next item
                    fi
                    ;;
                REINSTALLED:)
                    if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                        if [ "${i%-*}" = "${i}" ]; then
                            itemcount=0 # This is not a valid item so reset item count
                            MODE=
                        else
                            name=${i%-*}
                            version=${i##*-}
                            itemcount="$(expr $itemcount + 1)" # get ready for next item
                            if [ -n "$packages_reinstall" ]; then
                                packages_reinstall=$packages_reinstall"," # separator for next item
                            fi
                            packages_reinstall=$packages_reinstall"{\"name\":\"$name\",\"version\":\"$version\",\"repository\":\"${REPO}\"}"
                        fi
                    fi
                    ;;
                REMOVED:)
                    if [ "$(expr $linecount + 2)" -eq "$itemcount" ]; then
                        if [ "${i%:*}" = "${i}" ]; then
                            itemcount=0 # This is not a valid item so reset item count
                            MODE=
                        else
                            i=$(echo $i | tr -d :)
                            if [ -n "$packages_removed" ]; then
                                packages_removed=$packages_removed","
                            fi
                            packages_removed=$packages_removed"{\"name\":\"$i\",\"repository\":\"$(${PKG} query %R ${i})\","
                        fi
                    fi
                    if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                        packages_removed=$packages_removed"\"version\":\"$i\"}"
                        itemcount=$(expr $itemcount + 2) # get ready for next item
                    fi
                    ;;
                UPGRADED:)
                    if [ "$(expr $linecount + 4)" -eq "$itemcount" ]; then
                        if [ "${i%:*}" = "${i}" ]; then
                            itemcount=0 # This is not a valid item so reset item count
                            MODE=
                        else
                            i=$(echo $i | tr -d :)
                            if [ -n "$packages_upgraded" ]; then
                                packages_upgraded=$packages_upgraded","
                            fi
                            packages_upgraded=$packages_upgraded"{\"name\":\"$i\",\"repository\":\"${REPO}\","
                        fi
                    fi
                    if [ "$(expr $linecount + 3)" -eq "$itemcount" ]; then
                        packages_upgraded=$packages_upgraded"\"current_version\":\"$i\","
                    fi
                    if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                        packages_upgraded=$packages_upgraded"\"new_version\":\"$i\"}"
                        itemcount=$(expr $itemcount + 4) # get ready for next item
                    fi
                    ;;
                esac

                linecount=$(expr $linecount + 1)

                case $i in
                INSTALLED:|REMOVED:)
                    itemcount=$(expr $linecount + 2)
                    MODE=$i
                    ;;
                REINSTALLED:)
                    itemcount=$(expr $linecount + 1)
                    MODE=$i
                    ;;
                DOWNGRADED:|UPGRADED:)
                    itemcount=$(expr $linecount + 4)
                    MODE=$i
                    ;;
                esac
            done
        done < ${OUTFILE}

        # if we run twice give values as CSV for later processing
        download_size=$(grep 'to be downloaded' ${OUTFILE} | awk -F '[ ]' '{print $1$2}' | tr '\n' ',' | sed 's/,$//')

        # see if packages indicate a new version (not revision) of base / kernel
        LQUERY=$(${PKG} query %v opnsense-update)
        LQUERY=${LQUERY%%_*}
        RQUERY=$(${PKG} rquery %v opnsense-update)
        RQUERY=${RQUERY%%_*}

        if [ -n "${force_all}" -o "$(${PKG} version -t ${LQUERY} ${RQUERY})" = "<" ]; then
            kernel_to_reboot="${RQUERY}"
            base_to_reboot="${RQUERY}"
        fi

        if [ -z "${base_to_reboot}" ]; then
            if opnsense-update -cbf; then
                base_to_reboot="$(opnsense-update -v)"
            fi
        fi

        if [ -n "${base_to_reboot}" ]; then
            base_to_delete="$(opnsense-update -vb)"
            base_is_size="$(opnsense-update -bfSr ${base_to_reboot})"
            if [ "${base_to_reboot}${force_all}" != "${base_to_delete}" -a -n "${base_is_size}" ]; then
                # XXX this could be a downgrade or reinstall
                if [ -n "${packages_upgraded}" ]; then
                    packages_upgraded=${packages_upgraded}","
                fi
                packages_upgraded=${packages_upgraded}"{\"name\":\"base\",\"size\":\"${base_is_size}\","
                packages_upgraded=${packages_upgraded}"\"repository\":\"${product_repo}\","
                packages_upgraded=${packages_upgraded}"\"current_version\":\"${base_to_delete}\","
                packages_upgraded=${packages_upgraded}"\"new_version\":\"${base_to_reboot}\"}"
                needs_reboot="1"
            fi
        fi

        if [ -z "${kernel_to_reboot}" ]; then
            if opnsense-update -cfk; then
                kernel_to_reboot="$(opnsense-update -v)"
            fi
        fi

        if [ -n "${kernel_to_reboot}" ]; then
            kernel_to_delete="$(opnsense-update -vk)"
            kernel_is_size="$(opnsense-update -fkSr ${kernel_to_reboot})"
            if [ "${kernel_to_reboot}${force_all}" != "${kernel_to_delete}" -a -n "${kernel_is_size}" ]; then
                # XXX this could be a downgrade or reinstall
                if [ -n "${packages_upgraded}" ]; then
                    packages_upgraded=${packages_upgraded}","
                fi
                packages_upgraded=${packages_upgraded}"{\"name\":\"kernel\",\"size\":\"${kernel_is_size}\","
                packages_upgraded=${packages_upgraded}"\"repository\":\"${product_repo}\","
                packages_upgraded=${packages_upgraded}"\"current_version\":\"${kernel_to_delete}\","
                packages_upgraded=${packages_upgraded}"\"new_version\":\"${kernel_to_reboot}\"}"
                needs_reboot="1"
            fi
        fi
    fi
fi

packages_is_size="$(opnsense-update -SRp)"
if [ -n "${packages_is_size}" ]; then
    upgrade_major_version=$(opnsense-update -vR)

    upgrade_major_message=$(sed -e 's/"/\\&/g' -e "s/%%UPGRADE_RELEASE%%/${upgrade_major_version}/g" /usr/local/opnsense/data/firmware/upgrade.html 2> /dev/null | tr '\n' ' ')

    packages_to_delete="$(opnsense-update -vp)"
    if [ "${packages_to_delete}" != "${upgrade_major_version}" ]; then
        sets_upgraded="{\"name\":\"packages\",\"size\":\"${packages_is_size}\",\"current_version\":\"${packages_to_delete}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"
        upgrade_needs_reboot="1"
    fi

    kernel_to_delete="$(opnsense-update -vk)"
    if [ "${kernel_to_delete}" != "${upgrade_major_version}" ]; then
        kernel_is_size="$(opnsense-update -SRk)"
        if [ -n "${kernel_is_size}" ]; then
            if [ -n "${sets_upgraded}" ]; then
                sets_upgraded="${sets_upgraded},"
            fi
            sets_upgraded="${sets_upgraded}{\"name\":\"kernel\",\"size\":\"${kernel_is_size}\",\"current_version\":\"${kernel_to_delete}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"
            upgrade_needs_reboot="1"
        fi
    fi

    base_to_delete="$(opnsense-update -vb)"
    if [ "${base_to_delete}" != "${upgrade_major_version}" ]; then
        base_is_size="$(opnsense-update -SRb)"
        if [ -n "${base_is_size}" ]; then
            if [ -n "${sets_upgraded}" ]; then
                sets_upgraded="${sets_upgraded},"
            fi
            sets_upgraded="${sets_upgraded}{\"name\":\"base\",\"size\":\"${base_is_size}\",\"current_version\":\"${base_to_delete}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"
            upgrade_needs_reboot="1"
        fi
    fi
fi

# write our json structure
cat > ${JSONFILE} << EOF
{
    "api_version":"2",
    "connection":"${connection}",
    "downgrade_packages":[${packages_downgraded}],
    "download_size":"${download_size}",
    "last_check":"${last_check}",
    "needs_reboot":"${needs_reboot}",
    "new_packages":[${packages_new}],
    "os_version":"${os_version}",
    "product_id":"${product_id}",
    "product_target":"${product_target}",
    "product_version":"${product_version}",
    "product_abi":"${product_xabi}",
    "reinstall_packages":[${packages_reinstall}],
    "remove_packages":[${packages_removed}],
    "repository":"${repository}",
    "upgrade_major_message":"${upgrade_major_message}",
    "upgrade_major_version":"${upgrade_major_version}",
    "upgrade_needs_reboot":"${upgrade_needs_reboot}",
    "upgrade_packages":[${packages_upgraded}],
    "upgrade_sets":[${sets_upgraded}]
}
EOF

output_done
