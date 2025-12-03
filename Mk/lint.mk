# Copyright (c) 2025 Franco Fichtner <franco@opnsense.org>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

lint-desc:
.if defined(PLUGIN_DESC)
	@if [ ! -f ${.CURDIR}/${PLUGIN_DESC} ]; then \
		echo ">>> Missing ${PLUGIN_DESC}"; exit 1; \
	fi
.endif

lint-shell:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@for FILE in $$(find ${DIR} -name "*.sh" -type f); do \
	    if [ "$$(head $${FILE} | grep -c '^#!\/')" == "0" ]; then \
	        echo "Missing shebang in $${FILE}"; exit 1; \
	    fi; \
	    sh -n "$${FILE}" || exit 1; \
	done
.endif
.endfor

lint-xml:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@find ${DIR} -name "*.xml*" -type f -print0 | xargs -0 -n1 xmllint --noout
.endif
.endfor

lint-model:
.for DIR in src/opnsense/mvc/app/models
.if exists(${.CURDIR}/${DIR})
	@for MODEL in $$(find ${DIR} -depth 3 \
	    -name "*.xml"); do \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and (not(Required) or Required="N") and Default]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} has a spurious default value set"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and Default=""]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} has an empty default value set"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and BlankDesc="None"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} blank description is the default"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and BlankDesc and Required="Y"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} blank description not applicable on required field"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and BlankDesc and Multiple="Y"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} blank description not applicable on multiple field"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and Multiple="N"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} Multiple=N is the default"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and Required="N"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} Required=N is the default"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type and not(@type="ArrayField") and OptionValues[default[not(@value)] or multiple[not(@value)] or required[not(@value)]]]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} option element default/multiple/required without value attribute"; \
		done; \
		(xmllint $${MODEL} --xpath '//*[@type="CSVListField" and Mask and (not(MaskPerItem) or MaskPerItem=N)]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} uses Mask regex with MaskPerItem=N"; \
		done; \
		for TYPE in .\\AliasesField .\\DomainIPField HostnameField IPPortField NetworkField MacAddressField .\\RangeAddressField; do \
			(xmllint $${MODEL} --xpath '//*[@type="'$${TYPE}'" and FieldSeparator=","]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
				echo "$${MODEL}: $${LINE} FieldSeparator=, is the default"; \
			done; \
			(xmllint $${MODEL} --xpath '//*[@type="'$${TYPE}'" and AsList="N"]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
				echo "$${MODEL}: $${LINE} AsList=N is the default"; \
			done; \
		done; \
		(xmllint $${MODEL} --xpath '//ValidationMessage[not(substring(., string-length(.), 1) = ".")]' 2> /dev/null | grep '^<' || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} does not end with a dot"; \
		done; \
		(grep '<ValidationMessage>[a-z ]' $${MODEL} || true) | while read LINE; do \
			echo "$${MODEL}: $${LINE} does not start with an uppercase letter"; \
		done; \
		(xmllint $${MODEL} --xpath '/model/description' 2> /dev/null | wc -l | awk '{ print $$1 }' | grep -v '^1$$' || true) | while read LINE; do \
			echo "$${MODEL}: <description/> is not on a single line or missing"; \
		done; \
	done
.endif
.endfor

lint-acl:
	@${COREREFDIR}/Scripts/dashboard-acl.sh ${COREREFDIR}

SCRIPTDIRS!=	if [ -d ${.CURDIR}/src/opnsense/scripts ]; then find ${.CURDIR}/src/opnsense/scripts -type d -depth 1; fi

lint-exec:
.for DIR in ${.CURDIR}/src/etc/rc.d ${.CURDIR}/src/etc/rc.syshook.d ${SCRIPTDIRS}
.if exists(${DIR})
	@find ${DIR} -path '**/htdocs_default' -prune -o -type f \
	    ! -name "*.xml" ! -name "*.csv" ! -name "*.sql" -print0 | \
	    xargs -0 -t -n1 test -x || \
	    (echo "Missing executable permission in ${DIR}"; exit 1)
.endif
.endfor
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@git grep -e '[^li][^w>:]exec(' -e '^exec(' -e 'shell_exec(' \
	    -e '[^f]passthru(' -e '^passthru(' -e '[^._a-z]system(' \
	    -e '^system(' ':!*.js' ':!*.py' ':!*/contrib/*' \
	    ':!*/OPNsense/Core/Shell.php' ':!*/interfaces.lib.inc' \
	    ':!*/inc/certs.inc' ':!*/rc.configure_firmware' \
	    ':!*/rc.subr.d/recover' ${DIR} || true
.endif
.endfor

lint-php:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@${COREREFDIR}/contrib/parallel-lint/parallel-lint ${DIR}
.endif
.endfor

lint-plist:
.if exists(${.CURDIR}/plist)
	@mkdir -p ${WRKDIR}
	@${CORE_MAKE} DESTDIR=${DESTDIR} plist > ${WRKDIR}/plist.new
	@cat ${.CURDIR}/plist > ${WRKDIR}/plist.old
	@if ! cmp -s ${WRKDIR}/plist.old ${WRKDIR}/plist.new; then \
		diff -u ${WRKDIR}/plist.old ${WRKDIR}/plist.new || true; \
		echo ">>> Package file lists do not match.  Please run 'make plist-fix'." >&2; \
		rm ${WRKDIR}/plist.*; \
		exit 1; \
	fi
	@rm ${WRKDIR}/plist.*
.endif

lint: lint-plist lint-desc lint-shell lint-xml lint-model lint-acl lint-exec lint-php
