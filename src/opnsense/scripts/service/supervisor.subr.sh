#!/bin/sh

# Copyright (C) 2026 Deciso B.V.
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

# Source this file from a service's rc.conf.d file and call
# supervisor_enable.  The service must run in the foreground.

# A hard crash may leave the service-owned PID file behind and prevent the
# service from starting again.  daemon(8) invokes this mode on every restart
# so the stale PID file is removed immediately before executing the service.
if [ "${1:-}" = "exec" ]; then
    native_pidfile=$2
    shift 2
    [ -z "${native_pidfile}" ] || rm -f "${native_pidfile}"
    exec "$@"
fi

supervisor_get_pidfile()
{
    echo "/var/run/${name}.supervisor.pid"
}

supervisor_get_child_pidfile()
{
    echo "/var/run/${name}.daemon.pid"
}

supervisor_get_pid()
{
    check_pidfile "$(supervisor_get_pidfile)" /usr/sbin/daemon
}

supervisor_get_child_pid()
{
    check_pidfile "$(supervisor_get_child_pidfile)" "${command}" "${command_interpreter}"
}

supervisor_start()
{
    # Some service precmd hooks call run_rc_command() recursively.  Keep its
    # dispatcher state local while still allowing the hook to adjust flags.
    local command="${command}"
    local command_args="${command_args}"
    local rc_arg="${rc_arg}"
    local rc_flags="${rc_flags}"
    local rc_pid="${rc_pid}"
    local restart_count="${supervisor_restart_count:-5}"
    local restart_delay="${supervisor_restart_delay:-10}"
    local _cmd="${_cmd}"
    local _postcmd="${_postcmd}"
    local _precmd="${_precmd}"
    local _return="${_return}"
    local daemon_child_pidfile
    local supervisor_pid
    local supervisor_pidfile

    if [ -n "${supervisor_start_precmd}" ]; then
        ${supervisor_start_precmd} || return 1
    fi

    daemon_child_pidfile=$(supervisor_get_child_pidfile)
    supervisor_pidfile=$(supervisor_get_pidfile)
    supervisor_pid=$(supervisor_get_pid)
    if [ -n "${supervisor_pid}" ]; then
        echo "${name} supervisor already running as pid ${supervisor_pid}."
        return 1
    fi

    /usr/sbin/daemon -f -R "${restart_delay}" -C "${restart_count}" \
        -p "${daemon_child_pidfile}" -P "${supervisor_pidfile}" \
        /usr/local/opnsense/scripts/service/supervisor.subr.sh exec "${pidfile}" \
        ${command} ${rc_flags} ${command_args}
}

supervisor_stop()
{
    local supervisor_pid

    supervisor_pid=$(supervisor_get_pid)
    if [ -z "${supervisor_pid}" ]; then
        echo "${name} supervisor is not running."
        return 1
    fi

    echo "Stopping ${name} supervisor."
    kill -TERM "${supervisor_pid}" || return 1
    wait_for_pids "${supervisor_pid}"
    [ -z "${pidfile}" ] || rm -f "${pidfile}"
}

supervisor_reload()
{
    local daemon_child_pid
    local reload_signal

    daemon_child_pid=$(supervisor_get_child_pid)
    if [ -z "${daemon_child_pid}" ]; then
        echo "${name} supervised process is not running."
        return 1
    fi

    reload_signal=${supervisor_reload_signal:-${sig_reload:-HUP}}
    echo "Reloading ${name}."
    kill -"${reload_signal}" "${daemon_child_pid}"
}

supervisor_status()
{
    local supervisor_pid

    supervisor_pid=$(supervisor_get_pid)
    if [ -z "${supervisor_pid}" ]; then
        echo "${name} supervisor is not running."
        return 1
    fi

    echo "${name} supervisor is running as pid ${supervisor_pid}."
}

supervisor_enable()
{
    supervisor_start_precmd="${start_precmd}"
    start_precmd=
    start_cmd=supervisor_start
    stop_cmd=supervisor_stop
    reload_cmd=supervisor_reload
    status_cmd=supervisor_status
    case " ${extra_commands} " in
        *" reload "*) ;;
        *) extra_commands="${extra_commands} reload" ;;
    esac
}

# A service may use multiple supervised processes.  The caller supplies a
# whitespace-separated profile list and a callback which configures name,
# command, command_args and pidfile for a profile.  An optional callback
# determines which profiles are enabled.  Each profile is dispatched in a
# subshell so its rc.d state cannot leak into the aggregate service.
supervisor_profile_run()
(
    local profile=$1
    local action=$2

    ${supervisor_profile_configure} "${profile}" || return 1
    start_precmd=
    supervisor_enable
    run_rc_command "${action}"
)

supervisor_profile_is_running()
(
    ${supervisor_profile_configure} "$1" || return 1
    [ -n "$(supervisor_get_pid)" ]
)

# An aggregate service may retain its native rc.d script for cleanup, reload
# and status.  It must recognize processes started through the supervisor by
# using their native PID files.
supervisor_profiles_dispatch()
{
    local profile
    local result

    if [ -z "${supervisor_profiles_fallback}" ]; then
        echo "A fallback rc.d script is required for supervised profiles." >&2
        return 1
    fi

    case "${rc_arg}" in
        start)
            for profile in ${supervisor_profiles}; do
                if [ -n "${supervisor_profile_enabled}" ]; then
                    ${supervisor_profile_enabled} "${profile}" || continue
                fi
                supervisor_profile_run "${profile}" onestart || return 1
            done
            ;;
        stop)
            result=0
            # Check all profiles so a configuration change cannot orphan a
            # supervisor, then stop anything owned by the native rc.d script.
            for profile in ${supervisor_profiles}; do
                if supervisor_profile_is_running "${profile}"; then
                    supervisor_profile_run "${profile}" onestop || result=1
                fi
            done
            "${supervisor_profiles_fallback}" onestop || result=1
            return ${result}
            ;;
        reload|status)
            "${supervisor_profiles_fallback}" "${rc_arg}"
            ;;
    esac
}

supervisor_profiles_enable()
{
    start_cmd=supervisor_profiles_dispatch
    stop_cmd=supervisor_profiles_dispatch
    reload_cmd=supervisor_profiles_dispatch
    status_cmd=supervisor_profiles_dispatch
    case " ${extra_commands} " in
        *" reload "*) ;;
        *) extra_commands="${extra_commands} reload" ;;
    esac
}
