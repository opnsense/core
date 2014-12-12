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
# It generates different files to reflect repository/packages state
# /tmp/pkg_updates.output         ->  Output of pkg update
# /tmp/pkg_connection_issue       ->  Empty file to signal a connection issue
# /tmp/pkg_repository.error       ->  Empty file to signal a repository error
# /tmp/pkg_upgrades.output        ->  Output of pkg upgrade -n
# /tmp/pkg_updates.available      ->  File with content the number of upgrades or new installs 
# /tmp/pkg_core_update.available  ->  File with content the new OPNsense version number 


pkg_running=""
# Check if pkg is already runnig
pkg_running=`ps | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
      # start pkg update
      pkg update -f > /tmp/pkg_updates.output 2>&1 &
      pid=$!
      # wait for defined number of seconds for connection
      sleep 5
      # check if pkg is done, if not we have a connection issue
      pkg_running=`ps | grep $pid | grep -v "grep"`
      if [ "$pkg_running" == "" ]; then
        # Connection is ok
        # Lets cleanup old connection errors
        if [ -f /tmp/pkg_connection_issue ]; then
          rm /tmp/pkg_connection_issue
        fi
        repo_ok=`cat /tmp/pkg_updates.output | grep 'Unable to update repository'`
        if [ "$repo_ok" == "" ]; then
          # Repository can be used for updates
          # Check if earlier attemps left repository error file
          if [ -f /tmp/pkg_repository.error ]; then
            # Remove previous error file
            rm /tmp/pkg_repository.error
          fi
          # Now check if there are upgrades
          pkg upgrade -n > /tmp/pkg_upgrades.output
          updates=`cat /tmp/pkg_upgrades.output | grep 'The following' | awk -F '[ ]' '{print $3}'`
          if [ "$updates" == "" ]; then
            # There are no updates
            # Lets remove any leftover update files
            if [ -f /tmp/pkg_updates.available ]; then
              rm /tmp/pkg_updates.available
            fi
          else
            echo $updates > /tmp/pkg_updates.available
            opnsense_core_update=`cat /tmp/pkg_upgrades.output | grep 'opnsense:' | awk -F '[ ]' '{print $4}'`
            if [ "$opnsense_core_update" == "" ]; then
              # There is no update
              # Lets cleanup leftovers
              if [ -f /tmp/pkg_core_update.available] ]; then
                rm /tmp/pkg_core_update.available
              fi
            else
              echo $opnse_core_update > /tmp/pkg_core_update.available
            fi
          fi
        else
          # There is an issue with the repository
          # lets let other process know
          touch /tmp/pkg_repository.error
        fi
      else
          # We have an connection issue and can reach the pkg repository in timely fashion
          touch /tmp/pkg_connection_issue
          killall pkg
      fi
else
  # pkg is already running, quitting
fi
