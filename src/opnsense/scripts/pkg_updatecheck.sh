#!/bin/sh

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


# USAGE:
# Add this file to a CRON job to check for pakcage updates regularly
#
# This script generates a json structured file with the following content:
# connection: error|ok
# repository: error|ok
# last_ckeck: <date_time_stamp>
# updates: <#num_of_updates>
# core_version: current|<new core version>
# download_size: none|<size_of_total_downloads>
# extra_space_required: none|<size_of_total_extra_space_required>
# new_packages: array with { name: <package_name>, version: <package_version> }
# upgrade_packages: array with { name: <package_name>, current_version: <current_version>, new_version: <new_version> }

# TODO: Add object with items that will be removed or uninstalled

# Variables used
connection="error"
repository="error"
updates=""
core_version=""
pkg_running=""
packes_output=""
last_check="unknown"
packages_upgraded=""
packages_new=""
required_space="none"
download_size="none"
itemcount=0
linecount=0

# File location variables
package_json_output="/tmp/pkg_status.json"
tmp_pkg_output_file="/tmp/packages.output"
tmp_pkg_update_file="/tmp/pkg_updates.output"

# Check if pkg is already runnig
pkg_running=`ps | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
      # start pkg update
      pkg update -f > $tmp_pkg_update_file 2>&1 &
      pid=$!
      # wait for defined number of seconds for connection
      sleep 8
      # check if pkg is done, if not we have a connection issue
      pkg_running=`ps | grep $pid | grep -v "grep"`
      if [ "$pkg_running" == "" ]; then
        # Connection is ok
        connection="ok"
        repo_ok=`cat $tmp_pkg_update_file | grep 'Unable to update repository'`
        if [ "$repo_ok" == "" ]; then
          # Repository can be used for updates
          repository="ok"
          # Now check if there are upgrades
          pkg upgrade -n > $tmp_pkg_output_file
          updates=`cat $tmp_pkg_output_file | grep 'The following' | awk -F '[ ]' '{print $3}'` # > /tmp/pkg_upgrades.output
          #updates=`cat /tmp/pkg_upgrades.output | grep 'The following' | awk -F '[ ]' '{print $3}'`
          if [ "$updates" == "" ]; then
            # There are no updates
            updates="0"
          else
            core_version=`cat $tmp_pkg_output_file | grep 'opnsense:' | awk -F '[ ]' '{print $4}'` # Not really needed but usefull for fast version check
            required_space=`cat $tmp_pkg_output_file | grep 'The process will require' | awk -F '[ ]' '{print $5$6}'`
            if [ "$required_space" == "" ]; then
              required_space="none"
            fi
            download_size=`cat $tmp_pkg_output_file | grep 'to be downloaded' | awk -F '[ ]' '{print $1$2}'`
            if [ "$download_size" == "" ]; then
              download_size="none"
            fi
            # First check if there are new packages that need to be installed
            for i in $(cat $tmp_pkg_output_file); do
              if [ "$itemcount" -gt "$linecount" ]; then
                if [  `echo $linecount + 2 | bc` -eq "$itemcount" ]; then
                  if [ "`echo $i | grep ':'`" == "" ]; then
                    itemcount=0 # This is not a valid item so reset item count
                  else
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

            # Now check if there are upgrades to install
            for i in $(cat $tmp_pkg_output_file); do
              if [ "$itemcount" -gt "$linecount" ]; then
                if [  `echo $linecount + 4 | bc` -eq "$itemcount" ]; then
                  if [ "`echo $i | grep ':'`" == "" ]; then
                    itemcount=0 # This is not a valid item so reset item count
                  else
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
            if [ "$opnsense_core_update" == "" ]; then
              core_version="current"
            fi
          fi
        fi
      else
          # We have an connection issue and could not reach the pkg repository in timely fashion
          killall pkg
      fi
      last_check=`date`
      echo "{\"connection\":\"$connection\"},{\"repository\":\"$repository\"},{\"last_check\":,\"$last_check\"},{\"updates\":\"$updates\"},{\"core_version\":\"$core_version\"},{\"download_size\":\"$download_size\"},{\"extra_space_required\":\"$required_space\"},{\"new_packages\":[$packages_new]},{\"upgrade_packages\":[$packages_upgraded]}" > $package_json_output
else
  # pkg is already running, quitting
fi
