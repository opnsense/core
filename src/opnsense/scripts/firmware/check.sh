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
# connection: error|timeout|unauthenticated|misconfigured|unresolved|busy|ok
# repository: error|untrusted|unsigned|revoked|ok
# last_ckeck: <date_time_stamp>
# updates: <num_of_updates>
# download_size: <size_of_total_downloads>
# new_packages: array with { name: <package_name>, version: <package_version> }
# reinstall_packages: array with { name: <package_name>, version: <package_version> }
# remove_packages: array with { name: <package_name>, version: <package_version> }
# downgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

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
pkg_selected=${1}
pkg_upgraded=""
repository="error"
timeout_update=30
timeout_upgrade=60
updates=""
upgrade_needs_reboot="0"

pidfile=/tmp/pkg_update.pid
outfile=/tmp/pkg_update.out

pkg_running=$(pgrep -nF ${pidfile} 2> /dev/null)

if [ -z "${pkg_running}" ]; then
      timer=${timeout_update}
      pkg_running="started"
      : > ${outfile}

      daemon -p ${pidfile} -o ${outfile} pkg update -f

      while [ -n "${pkg_running}" -a $timer -ne 0 ]; do
        sleep 1
        pkg_running=$(pgrep -nF ${pidfile} 2> /dev/null)
        timer=$(expr $timer - 1)
      done

      if [ $timer -eq 0 ]; then
        # We have a connection issue and could not
        # reach the pkg repository in timely fashion
        # Kill all running pkg instances
        pkg_running=$(pgrep -nF ${pidfile} 2> /dev/null)
        if [ -n "${pkg_running}" ]; then
          pkill -F ${pidfile}
        fi
        connection="timeout"
      else
        # parse early errors
        if grep -q 'No address record' ${outfile}; then
          # DNS resolution failed
          connection="unresolved"
          timer=0
        elif grep -q 'Cannot parse configuration' ${outfile}; then
          # configuration error
          connection="misconfigured"
          timer=0
        elif grep -q 'Authentication error' ${outfile}; then
          # TLS or authentication error
          connection="unauthenticated"
          timer=0
        elif grep -q 'No trusted public keys found' ${outfile}; then
          # fingerprint mismatch
          repository="untrusted"
          connection="ok"
          timer=0
        elif grep -q 'At least one of the certificates has been revoked' ${outfile}; then
          # fingerprint mismatch
          repository="revoked"
          connection="ok"
          timer=0
        elif grep -q 'No signature found' ${outfile}; then
          # fingerprint not found
          repository="unsigned"
          connection="ok"
          timer=0
        elif grep -q 'Unable to update repository' ${outfile}; then
          # repository not found
          connection="ok"
          timer=0
        fi
      fi

      if [ $timer -gt 0 ]; then
        # connection is still ok
        connection="ok"

        timer=$timeout_upgrade
        pkg_running="started"
        : > ${outfile}

        # now check what happens when we would go ahead
        if [ -z "${pkg_selected}" ]; then
            daemon -p ${pidfile} -o ${outfile} pkg upgrade -n
        else
            # fetch before install lets us know more,
            # although not as fast as it should be...
            pkg fetch -y "${pkg_selected}" > /dev/null 2>&1
            daemon -p ${pidfile} -o ${outfile} pkg install -n "${pkg_selected}"
        fi

        while [ -n "${pkg_running}" -a $timer -ne 0 ]; do
          sleep 1
          pkg_running=$(pgrep -nF ${pidfile} 2> /dev/null)
          timer=$(expr $timer - 1)
        done

        ## check if timeout is not reached
        if [ $timer -gt 0 ]; then
          # Check for additional repository errors
          if ! grep -q 'Unable to update repository' ${outfile}; then
            # Repository can be used for updates
            repository="ok"
            updates=$(grep 'The following' ${outfile} | awk -F '[ ]' '{print $3}')
            if [ -z "${updates}" ]; then
              # There are no updates
              updates="0"
            else
              download_size=$(grep 'to be downloaded' ${outfile} | awk -F '[ ]' '{print $1$2}')

              # see if packages indicate a new version (not revision) of base / kernel
              LQUERY=$(pkg query %v opnsense-update)
              RQUERY=$(pkg rquery %v opnsense-update)
              if [ "${LQUERY%%_*}" != "${RQUERY%%_*}" ]; then
                kernel_to_reboot="${RQUERY%%_*}"
                base_to_reboot="${RQUERY%%_*}"
              fi

              MODE=

              for i in $(cat ${outfile} | tr '[' '(' | cut -d '(' -f1); do
                case ${MODE} in
                DOWNGRADED:)
                  if [ "$(expr $linecount + 4)" -eq "$itemcount" ]; then
                    if [ "${i%:*}" = "${i}" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                      MODE=
                    else
                      i=$(echo $i | tr -d :)
                      if [ -z "$packages_downgraded" ]; then
                        packages_downgraded="{\"name\":\"$i\"," # If it is the first item then we do not want a separator
                      else
                        packages_downgraded=$packages_downgraded", {\"name\":\"$i\","
                      fi
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
                      packages_new=$packages_new"{\"name\":\"$i\","
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
                      packages_reinstall=$packages_reinstall"{\"name\":\"$name\",\"version\":\"$version\"}"
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
                      packages_removed=$packages_removed"{\"name\":\"$i\","
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
                      if [ -z "$packages_upgraded" ]; then
                        if [ "$i" = "pkg" ]; then
                          # prevents leaking base / kernel advertising here
                          pkg_upgraded="yes"
                        fi
                        packages_upgraded="{\"name\":\"$i\"," # If it is the first item then we do not want a separator
                      else
                        packages_upgraded=$packages_upgraded", {\"name\":\"$i\","
                      fi
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
            fi

            # the main update from package will provide this during upgrade
            if [ -n "${pkg_upgraded}${pkg_selected}" ]; then
              base_to_reboot=
            elif [ -z "$base_to_reboot" ]; then
              if opnsense-update -cbf; then
                  base_to_reboot="$(opnsense-update -v)"
              fi
            fi

            if [ -n "$base_to_reboot" ]; then
              base_to_delete="$(opnsense-version -v base)"
              base_is_size="$(opnsense-update -bfSr $base_to_reboot)"
              if [ "$base_to_reboot" != "$base_to_delete" -a -n "$base_is_size" ]; then
                if [ -z "${packages_upgraded}" ]; then
                  packages_upgraded="{\"name\":\"base\"," # If it is the first item then we do not want a separator
                else
                  packages_upgraded=$packages_upgraded", {\"name\":\"base\","
                fi
                packages_upgraded=$packages_upgraded"\"size\":\"$base_is_size\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$base_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$base_to_reboot\"}"
                updates=$(expr $updates + 1)
                upgrade_needs_reboot="1"
              fi
            fi

            # the main update from package will provide this during upgrade
            if [ -n "${pkg_upgraded}${pkg_selected}" ]; then
              kernel_to_reboot=
            elif [ -z "$kernel_to_reboot" ]; then
              if opnsense-update -cfk; then
                  kernel_to_reboot="$(opnsense-update -v)"
              fi
            fi

            if [ -n "$kernel_to_reboot" ]; then
              kernel_to_delete="$(opnsense-version -v kernel)"
              kernel_is_size="$(opnsense-update -fkSr $kernel_to_reboot)"
              if [ "$kernel_to_reboot" != "$kernel_to_delete" -a -n "$kernel_is_size" ]; then
                if [ -z "${packages_upgraded}" ]; then
                  packages_upgraded="{\"name\":\"kernel\"," # If it is the first item then we do not want a separator
                else
                  packages_upgraded=$packages_upgraded", {\"name\":\"kernel\","
                fi
                packages_upgraded=$packages_upgraded"\"size\":\"$kernel_is_size\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$kernel_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$kernel_to_reboot\"}"
                updates=$(expr $updates + 1)
                upgrade_needs_reboot="1"
              fi
            fi
          fi
        else
          # We have a connection issue and could not reach the pkg repository in timely fashion
          # Kill all running pkg instances
          pkg_running=$(pgrep -nF ${pidfile} 2> /dev/null)
          if [ -n "${pkg_running}" ]; then
            pkill -F ${pidfile}
          fi
        fi
      fi

      # XXX use opnsense-update -SRp to check for download size before advertising
      upgrade_major_message=$(cat /usr/local/opnsense/firmware-message 2> /dev/null | sed 's/"/\\&/g' | tr '\n' ' ')
      upgrade_major_version=$(cat /usr/local/opnsense/firmware-upgrade 2> /dev/null)

      product_version=$(opnsense-version -v)
      product_name=$(opnsense-version -n)
      os_version=$(uname -sr)
      last_check=$(date)
else
  connection=busy
fi

# write our json structure
cat << EOF
{
	"connection":"$connection",
	"downgrade_packages":[$packages_downgraded],
	"download_size":"$download_size",
	"last_check":"$last_check",
	"new_packages":[$packages_new],
	"os_version":"$os_version",
	"product_name":"$product_name",
	"product_version":"$product_version",
	"reinstall_packages":[$packages_reinstall],
	"remove_packages":[$packages_removed],
	"repository":"$repository",
	"updates":"$updates",
	"upgrade_major_message":"$upgrade_major_message",
	"upgrade_major_version":"$upgrade_major_version",
	"upgrade_needs_reboot":"$upgrade_needs_reboot",
	"upgrade_packages":[$packages_upgraded]
}
EOF
