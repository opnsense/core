#!/bin/sh

# Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
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
# connection: error|ok
# repository: error|ok
# last_ckeck: <date_time_stamp>
# updates: <num_of_updates>
# download_size: <size_of_total_downloads>
# new_packages: array with { name: <package_name>, version: <package_version> }
# reinstall_packages: array with { name: <package_name>, version: <package_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

# TODO: Add object with items that will be removed

# Variables used
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
packages_downgraded=""
packages_new=""
download_size=""
itemcount=0
linecount=0
timer=0
timeout=60 # Wait for a maximum number of seconds to determine connection issues

# File location variables
tmp_pkg_output_file="/tmp/packages.output"
tmp_pkg_update_file="/tmp/pkg_updates.output"

# Check if pkg is already runnig
pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
      # start pkg update
      pkg update -f > $tmp_pkg_update_file &
      pkg_running="started" # Set running state to arbitrary value
      timer=$timeout # Reset our timer

      # Timeout loop for pkg update -f
      while [ "$pkg_running" != "" ] && [ $timer -ne 0 ];
      do
        sleep 1 # wait for 1 second
        pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
        timer=`echo $timer - 1 | bc`
      done

      ## check if timeout is not reached
      if [ $timer -gt 0 ] ; then
        # Connection is ok
        connection="ok"
        # Now check if there are upgrades
        pkg upgrade -n > $tmp_pkg_output_file &
        # Reset timer before getting upgrade info
        timer=$timeout # Reset our timer
        pkg_running="started" # Set running state to arbitrary value

        # Timeout loop for pkg upgrade -n
        while [ "$pkg_running" != "" ] && [ $timer -ne 0 ];
        do
          sleep 1 # wait for 1 second
          #pkg_running=`ps | grep 'pkg update -f' | grep -v 'grep' | tail -n 1 | awk -F '[ ]' '{print $1}'`
          pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
          timer=`echo $timer - 1 | bc`
        done

        ## check if timeout is not reached
        if [ $timer -gt 0 ] ; then
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

              LQUERY=$(pkg query %v opnsense-update)
              RQUERY=$(pkg rquery %v opnsense-update)
              if [ "${LQUERY%%_*}" != "${RQUERY%%_*}" ]; then
                kernel_to_reboot="${RQUERY%%_*}"
                base_to_reboot="${RQUERY%%_*}"
              fi

              # First check if there are new packages that need to be installed
              for i in $(cat $tmp_pkg_output_file); do
                if [ "$itemcount" -gt "$linecount" ]; then
                  if [  `echo $linecount + 2 | bc` -eq "$itemcount" ]; then
                    if [ "`echo $i | grep ':'`" == "" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                    else
                      i=`echo $i | tr -d :`
                      #echo "name:$i"
                      if [ "$packages_new" == "" ]; then
                        packages_new=$packages_new"{\"name\":\"$i\"," # If it is the first item then we do not want a seperator
                      else
                        packages_new=$packages_new", {\"name\":\"$i\","
                      fi
                    fi
                  fi
                  if [  `echo $linecount + 1 | bc` -eq "$itemcount" ]; then
                    packages_new=$packages_new"\"version\":\"$i\"}"
                    itemcount=`echo $itemcount + 2 | bc` # Get ready for next item
                  fi
                fi
                linecount=`echo $linecount + 1 | bc`
                if [ "$i" == "INSTALLED:" ]; then
                  itemcount=`echo $linecount + 2 | bc`
                fi
              done

              # Check if there are packages that need to be reinstalled
              for i in $(cat $tmp_pkg_output_file | cut -d '(' -f1); do
                if [ "$itemcount" -gt "$linecount" ]; then
                  if [  `echo $linecount + 1 | bc` -eq "$itemcount" ]; then
                    if [ "`echo $i | grep '-'`" == "" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                    else
                      name=${i%-*}
                      version=${i##*-}
                      itemcount=`echo $itemcount + 1 | bc` # Get ready for next item
                      if [ "$packages_reinstall" == "" ]; then
                        packages_reinstall=$packages_reinstall"{\"name\":\"$name\"," # If it is the first item then we do not want a seperator
                        packages_reinstall=$packages_reinstall"\"version\":\"$version\"}"
                      else
                        packages_reinstall=$packages_reinstall", {\"name\":\"$name\","
                        packages_reinstall=$packages_reinstall"\"version\":\"$version\"}"
                      fi
                    fi
                  fi
                fi
                linecount=`echo $linecount + 1 | bc`
                if [ "$i" == "REINSTALLED:" ]; then
                  itemcount=`echo $linecount + 1 | bc`
                fi
              done

              # Now check if there are upgrades to install
              for i in $(cat $tmp_pkg_output_file); do
                if [ "$itemcount" -gt "$linecount" ]; then
                  if [  `echo $linecount + 4 | bc` -eq "$itemcount" ]; then
                    if [ "`echo $i | grep ':'`" == "" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                    else
                      i=`echo $i | tr -d :`
                      if [ "$packages_upgraded" == "" ]; then
                        packages_upgraded=$packages_upgraded"{\"name\":\"$i\"," # If it is the first item then we do not want a seperator
                      else
                        packages_upgraded=$packages_upgraded", {\"name\":\"$i\","
                      fi
                    fi
                  fi
                  if [  `echo $linecount + 3 | bc` -eq "$itemcount" ]; then
                    packages_upgraded=$packages_upgraded"\"current_version\":\"$i\","
                  fi
                  if [  `echo $linecount + 1 | bc` -eq "$itemcount" ]; then
                    packages_upgraded=$packages_upgraded"\"new_version\":\"$i\"}"
                    itemcount=`echo $itemcount + 4 | bc` # Get ready for next item
                  fi
                fi
                linecount=`echo $linecount + 1 | bc`
                if [ "$i" == "UPGRADED:" ]; then
                  itemcount=`echo $linecount + 4 | bc`
                fi
              done

              # Now check if there are downgrades to install
              for i in $(cat $tmp_pkg_output_file); do
                if [ "$itemcount" -gt "$linecount" ]; then
                  if [  `echo $linecount + 4 | bc` -eq "$itemcount" ]; then
                    if [ "`echo $i | grep ':'`" == "" ]; then
                      itemcount=0 # This is not a valid item so reset item count
                    else
                      i=`echo $i | tr -d :`
                      if [ "$packages_downgraded" == "" ]; then
                        packages_downgraded=$packages_downgraded"{\"name\":\"$i\"," # If it is the first item then we do not want a seperator
                      else
                        packages_downgraded=$packages_downgraded", {\"name\":\"$i\","
                      fi
                    fi
                  fi
                  if [  `echo $linecount + 3 | bc` -eq "$itemcount" ]; then
                    packages_downgraded=$packages_downgraded"\"current_version\":\"$i\","
                  fi
                  if [  `echo $linecount + 1 | bc` -eq "$itemcount" ]; then
                    packages_downgraded=$packages_downgraded"\"new_version\":\"$i\"}"
                    itemcount=`echo $itemcount + 4 | bc` # Get ready for next item
                  fi
                fi
                linecount=`echo $linecount + 1 | bc`
                if [ "$i" == "DOWNGRADED:" ]; then
                  itemcount=`echo $linecount + 4 | bc`
                fi
              done
            fi
            if opnsense-update -cbf; then
              # the main update from package will override this during upgrade
              if [ -z "$base_to_reboot" ]; then
                  base_to_reboot="$(opnsense-update -v)"
              fi
            fi
            if [ -n "$base_to_reboot" ]; then
              base_to_delete="$(opnsense-update -bv)"
              base_is_size="$(opnsense-update -bfS)"
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
            if opnsense-update -cfk; then
              # the main update from package will override this during upgrade
              if [ -z "$kernel_to_reboot" ]; then
                  kernel_to_reboot="$(opnsense-update -v)"
              fi
            fi
            if [ -n "$kernel_to_reboot" ]; then
              kernel_to_delete="$(opnsense-update -kv)"
              kernel_is_size="$(opnsense-update -fkS)"
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
      else
          # We have an connection issue and could not reach the pkg repository in timely fashion
          # Kill all running pkg instances
          pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
          if [ "$pkg_running" != "" ]; then
            killall pkg
          fi
      fi

      product_version=$(cat /usr/local/opnsense/version/opnsense)
      product_name=$(cat /usr/local/opnsense/version/opnsense.name)
      os_version=$(uname -sr)
      last_check=$(date)

      # Write our json structure to disk
      echo "{\"connection\":\"$connection\",\"repository\":\"$repository\",\"product_version\":\"$product_version\",\"product_name\":\"$product_name\",\"os_version\":\"$os_version\",\"last_check\":\"$last_check\",\"updates\":\"$updates\",\"download_size\":\"$download_size\",\"new_packages\":[$packages_new],\"reinstall_packages\":[$packages_reinstall],\"upgrade_packages\":[$packages_upgraded],\"downgrade_packages\":[$packages_downgraded],\"upgrade_needs_reboot\":\"$upgrade_needs_reboot\"}"
fi
