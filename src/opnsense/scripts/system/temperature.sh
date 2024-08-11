#!/bin/sh

# Copyright (C) 2020 Franco Fichtner <franco@opnsense.org>
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

CMD=${1}

SYSCTLS="
dev.cpu.0.temperature
hw.acpi.thermal.tz0.temperature
hw.temperature.CPU
"

# count_cpu gets the number of cpu
count_cpu() {
	local n
	n=$(sysctl -n hw.ncpu)

	if [ $? -ne 0 ]
	then
		n=0
	fi
	eval "$1=$n"
}

read_cpu_temperatures() {
	local ncpu=0
	count_cpu ncpu
	if [ $ncpu -gt 0 ]
	then
		sysctl -i -e $(jot -w 'dev.cpu.%d.temperature' $ncpu 0)
	fi
	sysctl -i -e hw.temperature.CPU
}

# count_thermal_zones gets the number of thermal zones
count_thermal_zones() {
	local n=0
	while true
	do
		sysctl -n hw.acpi.thermal.tz${n}.temperature > /dev/null 2>/dev/null
		if [ $? -ne 0 ]
		then
			break
		fi
		n=$((n + 1))
	done
	eval "$1=$n"
}

read_thermal_zone_temperatures() {
	local ntz=0
	count_thermal_zones ntz
	if [ $ntz -gt 0 ]; then
		sysctl -i -e $(jot -w 'hw.acpi.thermal.tz%d.temperature' $ntz 0)
	fi
}

read_temperatures() {
	read_cpu_temperatures
	read_thermal_zone_temperatures
}

if [ "${CMD}" = 'rrd' ]; then
	for SYSCTL in ${SYSCTLS}; do
		TEMP=$(sysctl -i -n ${SYSCTL} | sed 's/C//g')
		if [ -n "${TEMP}" ]; then
			echo ${TEMP}
			break
		fi
	done
else
	read_temperatures
fi
