#!/bin/sh

# Copyright (C) 2015-2018 Franco Fichtner <franco@opnsense.org>
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
# repository: error|ok
# last_ckeck: <date_time_stamp>
# updates: <num_of_updates>
# download_size: <size_of_total_downloads>
# new_packages: array with { name: <package_name>, version: <package_version> }
# reinstall_packages: array with { name: <package_name>, version: <package_version> }
# remove_packages: array with { name: <package_name>, version: <package_version> }
# downgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

connection="error"
repository="error"
upgrade_needs_reboot="0"
kernel_to_reboot=""
base_to_reboot=""
updates=""
pkg_running=""
packes_output=""
last_check="unknown"
packages_upgraded=""
pkg_upgraded=""
packages_downgraded=""
packages_new=""
download_size=""
itemcount=0
linecount=0
timeout_update=30
timeout_upgrade=60

tmp_pkg_output_file="/tmp/packages.output"
tmp_pkg_update_file="/tmp/pkg_updates.output"

# Check if pkg is already runnig
pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
      # start pkg update
      pkg update -f > $tmp_pkg_update_file 2>&1 &
      timer=$timeout_update
      pkg_running="started"

      # Timeout loop for pkg update -f
      while [ "$pkg_running" != "" ] && [ $timer -ne 0 ]; do
        sleep 1 # wait for 1 second
        pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
        timer=`echo $timer - 1 | bc`
      done

      if [ $timer -eq 0 ]; then
        # We have an connection issue and could not
        # reach the pkg repository in timely fashion
        # Kill all running pkg instances
        pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
        if [ "$pkg_running" != "" ]; then
          killall pkg
        fi
        connection="timeout"
      else
        # parse early errors
        if grep -q 'No address record' $tmp_pkg_update_file; then
          # DNS resolution failed
          connection="unresolved"
          timer=0
        elif grep -q 'Cannot parse configuration' $tmp_pkg_update_file; then
          # configuration error
          connection="misconfigured"
          timer=0
        elif grep -q 'Authentication error' $tmp_pkg_update_file; then
          # TLS or authentication error
          connection="unauthenticated"
          timer=0
        fi
      fi

      if [ $timer -gt 0 ]; then
        # connection is still ok
        connection="ok"
        # now check what happens when we would go ahead
        if [ -z "${1}" ]; then
            pkg upgrade -n > $tmp_pkg_output_file &
        else
            # fetch before install lets us know more
            pkg fetch -y "${1}" > $tmp_pkg_output_file &
            pkg install -n "${1}" > $tmp_pkg_output_file &
	fi
        timer=$timeout_upgrade
        pkg_running="started"

        # Timeout loop for pkg upgrade -n
        while [ "$pkg_running" != "" ] && [ $timer -ne 0 ]; do
          sleep 1 # wait for 1 second
          pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
          timer=`echo $timer - 1 | bc`
        done

        ## check if timeout is not reached
        if [ $timer -gt 0 ]; then
          # Check for additional repository errors
          repo_ok=`cat $tmp_pkg_output_file | grep 'Unable to update repository'`
          if [ "$repo_ok" == "" ]; then
            # Repository can be used for updates
            repository="ok"
            updates=`cat $tmp_pkg_output_file | grep 'The following' | awk -F '[ ]' '{print $3}'`
            if [ "$updates" == "" ]; then
              # There are no updates
              updates="0"
            else
              download_size=`cat $tmp_pkg_output_file | grep 'to be downloaded' | awk -F '[ ]' '{print $1$2}'`

              # see if packages indicate a new version (not revision) of base / kernel
              LQUERY=$(pkg query %v opnsense-update)
              RQUERY=$(pkg rquery %v opnsense-update)
              if [ "${LQUERY%%_*}" != "${RQUERY%%_*}" ]; then
                kernel_to_reboot="${RQUERY%%_*}"
                base_to_reboot="${RQUERY%%_*}"
              fi

              MODE=

              for i in $(cat $tmp_pkg_output_file | cut -d '(' -f1); do
                case ${MODE} in
                DOWNGRADED:)
                  if [ "$(expr $linecount + 4)" -eq "$itemcount" ]; then
                    if [ "${i%:*}" = "${i}" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                      MODE=
                    else
                      i=`echo $i | tr -d :`
                      if [ -z "$packages_downgraded" ]; then
                        packages_downgraded=$packages_downgraded"{\"name\":\"$i\"," # If it is the first item then we do not want a seperator
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
                      i=`echo $i | tr -d :`
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
                  if [ "$(expr $linecount + 1)" -eq "$itemcount" ]; then
                    if [ "${i%-*}" = "${i}" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                      MODE=
                    else
                      name=${i%-*}
                      version=${i##*-}
                      itemcount="$(expr $itemcount + 1)" # get ready for next item
                      if [ -n "$packages_remove" ]; then
                        packages_remove=$packages_remove"," # separator for next item
                      fi
                      packages_remove=$packages_remove"{\"name\":\"$name\",\"version\":\"$version\"}"
                    fi
                  fi
                  ;;
                UPGRADED:)
                  if [ "$(expr $linecount + 4)" -eq "$itemcount" ]; then
                    if [ "${i%:*}" = "${i}" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                      MODE=
                    else
                      i=`echo $i | tr -d :`
                      if [ -z "$packages_upgraded" ]; then
                        if [ "$i" = "pkg" ]; then
                          # prevents leaking base / kernel advertising here
                          pkg_upgraded="yes"
                        fi
                        packages_upgraded=$packages_upgraded"{\"name\":\"$i\"," # If it is the first item then we do not want a seperator
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
                INSTALLED:)
                  itemcount=$(expr $linecount + 2)
                  MODE=$i
                  ;;
                REINSTALLED:|REMOVED:)
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
            if [ -n "$pkg_upgraded" ]; then
              base_to_reboot=
            elif [ -z "$base_to_reboot" ]; then
              if opnsense-update -cbf; then
                  base_to_reboot="$(opnsense-update -v)"
                  base_to_reboot="${base_to_reboot%-*}"
              fi
            fi

            if [ -n "$base_to_reboot" ]; then
              base_to_delete="$(opnsense-update -bv)"
              base_to_delete="${base_to_delete%-*}"
              base_is_size="$(opnsense-update -bfSr $base_to_reboot)"
              upgrade_needs_reboot="1"
              if [ "$base_to_reboot" != "$base_to_delete" -a -n "$base_is_size" ]; then
                if [ "$packages_upgraded" == "" ]; then
                  packages_upgraded=$packages_upgraded"{\"name\":\"base\"," # If it is the first item then we do not want a seperator
                else
                  packages_upgraded=$packages_upgraded", {\"name\":\"base\","
                fi
                packages_upgraded=$packages_upgraded"\"size\":\"$base_is_size\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$base_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$base_to_reboot\"}"
                updates=$(expr $updates + 1)
              fi
            fi

            # the main update from package will provide this during upgrade
            if [ -n "$pkg_upgraded" ]; then
              kernel_to_reboot=
            elif [ -z "$kernel_to_reboot" ]; then
              if opnsense-update -cfk; then
                  kernel_to_reboot="$(opnsense-update -v)"
                  kernel_to_reboot="${kernel_to_reboot%-*}"
              fi
            fi

            if [ -n "$kernel_to_reboot" ]; then
              kernel_to_delete="$(opnsense-update -kv)"
              kernel_to_delete="${kernel_to_delete%-*}"
              kernel_is_size="$(opnsense-update -fkSr $kernel_to_reboot)"
              upgrade_needs_reboot="1"
              if [ "$kernel_to_reboot" != "$kernel_to_delete" -a -n "$kernel_is_size" ]; then
                if [ "$packages_upgraded" == "" ]; then
                  packages_upgraded=$packages_upgraded"{\"name\":\"kernel\"," # If it is the first item then we do not want a seperator
                else
                  packages_upgraded=$packages_upgraded", {\"name\":\"kernel\","
                fi
                packages_upgraded=$packages_upgraded"\"size\":\"$kernel_is_size\","
                packages_upgraded=$packages_upgraded"\"current_version\":\"$kernel_to_delete\","
                packages_upgraded=$packages_upgraded"\"new_version\":\"$kernel_to_reboot\"}"
                updates=$(expr $updates + 1)
              fi
            fi
          fi
        else
          # We have an connection issue and could not reach the pkg repository in timely fashion
          # Kill all running pkg instances
          pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
          if [ "$pkg_running" != "" ]; then
            killall pkg
          fi
        fi
      fi

      upgrade_major_message=$(cat /usr/local/opnsense/firmware-message 2> /dev/null | sed 's/"/\\&/g' | tr '\n' ' ')
      upgrade_major_version=$(cat /usr/local/opnsense/firmware-upgrade 2> /dev/null)
      product_version=$(cat /usr/local/opnsense/version/opnsense)
      product_name=$(cat /usr/local/opnsense/version/opnsense.name)
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
	"remove_packages":[$packages_remove],
	"repository":"$repository",
	"updates":"$updates",
	"upgrade_major_message":"$upgrade_major_message",
	"upgrade_major_version":"$upgrade_major_version",
	"upgrade_needs_reboot":"$upgrade_needs_reboot",
	"upgrade_packages":[$packages_upgraded]
}
EOF
