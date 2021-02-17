#!/bin/sh

# Copyright (C) 2015-2021 Franco Fichtner <franco@opnsense.org>
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
# connection: error|unauthenticated|misconfigured|unresolved|ok
# repository: error|untrusted|unsigned|revoked|incomplete|ok
# last_ckeck: <date_time_stamp>
# download_size: <size_of_total_downloads>[,<size_of_total_downloads>]
# new_packages: array with { name: <package_name>, version: <package_version> }
# reinstall_packages: array with { name: <package_name>, version: <package_version> }
# remove_packages: array with { name: <package_name>, version: <package_version> }
# downgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

JSONFILE="/tmp/pkg_upgrade.json"
JSONRETURN=${1}
LOCKFILE="/tmp/pkg_upgrade.progress"
OUTFILE="/tmp/pkg_update.out"
TEE="/usr/bin/tee -a"

rm -f ${JSONFILE}
: > ${LOCKFILE}

base_to_reboot=""
connection="error"
download_size=""
itemcount=0
kernel_to_reboot=""
last_check="unknown"
linecount=0
packages_downgraded=""
packages_new=""
packages_upgraded=""
product_repo="OPNsense"
repository="error"
sets_upgraded=""
upgrade_needs_reboot="0"

product_suffix="-$(pluginctl -g system.firmware.type)"
if [ "${product_suffix}" = "-" ]; then
    product_suffix=
fi

last_check=$(date)
os_version=$(uname -sr)
product_id=$(opnsense-version -n)
product_target=opnsense${product_suffix}
product_version=$(opnsense-version -v)

echo "***GOT REQUEST TO CHECK FOR UPDATES***" >> ${LOCKFILE}

echo -n "Fetching changelog information, please wait... " >> ${LOCKFILE}
if /usr/local/opnsense/scripts/firmware/changelog.sh fetch >> ${LOCKFILE} 2>&1; then
    echo "done" >> ${LOCKFILE}
fi

: > ${OUTFILE}
(pkg update -f 2>&1) | ${TEE} ${LOCKFILE} ${OUTFILE}

(pkg unlock -y pkg 2>&1) | ${TEE} ${LOCKFILE}
(pkg upgrade -r ${product_repo} -Uy pkg 2>&1) | ${TEE} ${LOCKFILE}
(pkg lock -y pkg 2>&1) | ${TEE} ${LOCKFILE}

# XXX do we have to call update again if pkg was updated?

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
elif grep -q 'Unable to update repository' ${OUTFILE}; then
    # repository not found
    connection="ok"
else
    # connection is still ok
    connection="ok"

    : > ${OUTFILE}

    # now check what happens when we would go ahead
    (pkg upgrade -Un 2>&1) | ${TEE} ${LOCKFILE} ${OUTFILE}

    if [ "${product_id}" != "${product_target}" ]; then
        echo "Targeting new release type: ${product_target}" | ${TEE} ${LOCKFILE}
        # fetch before install lets us know more
        (pkg fetch -r ${product_repo} -Uy "${product_target}" 2>&1) | ${TEE} ${LOCKFILE}
        (pkg install -r ${product_repo} -Un "${product_target}" 2>&1) | ${TEE} ${LOCKFILE} ${OUTFILE}
    else
        echo "A release type change is not required." | ${TEE} ${LOCKFILE}
    fi

    # Check for additional repository errors
    if grep -q 'Unable to update repository' ${OUTFILE}; then
        repository="error" # already set but reset here for clarity
    elif grep -q "No packages available to install matching..${product_target}" ${OUTFILE}; then
        repository="incomplete"
    else
        # Repository can be used for updates
        repository="ok"

        if [ -n "$(grep 'The following' ${OUTFILE} | awk -F '[ ]' '{print $3}')" ]; then # XXX not strictly needed
            # if we run twice give values as CSV for later processing
            download_size=$(grep 'to be downloaded' ${OUTFILE} | awk -F '[ ]' '{print $1$2}' | tr '\n' ',' | sed 's/,$//')

            # see if packages indicate a new version (not revision) of base / kernel
            LQUERY=$(pkg query %v opnsense-update)
            RQUERY=$(pkg rquery %v opnsense-update)
            if [ "${LQUERY%%_*}" != "${RQUERY%%_*}" ]; then
                kernel_to_reboot="${RQUERY%%_*}"
                base_to_reboot="${RQUERY%%_*}"
            fi

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
                                packages_removed=$packages_removed"{\"name\":\"$i\",\"repository\":\"$(pkg query %R ${i})\","
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
        fi

        if [ -z "$base_to_reboot" ]; then
            if opnsense-update -cbf; then
                base_to_reboot="$(opnsense-update -v)"
            fi
        fi

        if [ -n "$base_to_reboot" ]; then
            base_to_delete="$(opnsense-version -v base)"
            base_is_size="$(opnsense-update -bfSr $base_to_reboot)"
            if [ "$base_to_reboot" != "$base_to_delete" -a -n "$base_is_size" ]; then
                if [ -n "${packages_upgraded}" ]; then
                    packages_upgraded=$packages_upgraded","
                fi
                packages_upgraded=$packages_upgraded"{\"name\":\"base\",\"size\":\"$base_is_size\","
                packages_upgraded=$packages_upgraded"\"repository\":\"${product_repo}\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$base_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$base_to_reboot\"}"
                upgrade_needs_reboot="1"
            fi
        fi

        if [ -z "$kernel_to_reboot" ]; then
            if opnsense-update -cfk; then
                kernel_to_reboot="$(opnsense-update -v)"
            fi
        fi

        if [ -n "$kernel_to_reboot" ]; then
            kernel_to_delete="$(opnsense-version -v kernel)"
            kernel_is_size="$(opnsense-update -fkSr $kernel_to_reboot)"
            if [ "$kernel_to_reboot" != "$kernel_to_delete" -a -n "$kernel_is_size" ]; then
                if [ -n "${packages_upgraded}" ]; then
                    packages_upgraded=$packages_upgraded","
                fi
                packages_upgraded=$packages_upgraded"{\"name\":\"kernel\",\"size\":\"$kernel_is_size\","
                packages_upgraded=$packages_upgraded"\"repository\":\"${product_repo}\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$kernel_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$kernel_to_reboot\"}"
                upgrade_needs_reboot="1"
            fi
        fi
    fi
fi

packages_is_size="$(opnsense-update -SRp)"
if [ -n "${packages_is_size}" ]; then
    upgrade_major_message=$(cat /usr/local/opnsense/firmware-message 2> /dev/null | sed 's/"/\\&/g' | tr '\n' ' ')
    upgrade_major_version=$(cat /usr/local/opnsense/firmware-upgrade 2> /dev/null)
    sets_upgraded="{\"name\":\"packages\",\"size\":\"${packages_is_size}\",\"current_version\":\"${product_version}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"

    kernel_to_delete="$(opnsense-version -v kernel)"
    if [ "${kernel_to_delete}" != "${upgrade_major_version}" ]; then
        kernel_is_size="$(opnsense-update -SRk)"
        if [ -n "${kernel_is_size}" ]; then
            sets_upgraded="${sets_upgraded},{\"name\":\"kernel\",\"size\":\"${kernel_is_size}\",\"current_version\":\"${kernel_to_delete}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"
        fi
    fi

    base_to_delete="$(opnsense-version -v base)"
    if [ "${base_to_delete}" != "${upgrade_major_version}" ]; then
        base_is_size="$(opnsense-update -SRb)"
        if [ -n "${base_is_size}" ]; then
            sets_upgraded="${sets_upgraded},{\"name\":\"base\",\"size\":\"${base_is_size}\",\"current_version\":\"${base_to_delete}\",\"new_version\":\"${upgrade_major_version}\",\"repository\":\"${product_repo}\"}"
        fi
    fi
fi

# write our json structure
cat > ${JSONFILE} << EOF
{
    "connection":"${connection}",
    "downgrade_packages":[${packages_downgraded}],
    "download_size":"${download_size}",
    "last_check":"${last_check}",
    "new_packages":[${packages_new}],
    "os_version":"${os_version}",
    "product_id":"${product_id}",
    "product_target":"${product_target}",
    "product_version":"${product_version}",
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

if [ -n "${JSONRETURN}" ]; then
    cat ${JSONFILE}
fi

echo '***DONE***' >> ${LOCKFILE}
