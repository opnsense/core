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

lint-shell:
	@find ${.CURDIR}/src ${.CURDIR}/Scripts \
	    -name "*.sh" -type f -print0 | xargs -0 -n1 sh -n

lint-xml:
	@find ${.CURDIR}/src ${.CURDIR}/Scripts \
	    -name "*.xml*" -type f -print0 | xargs -0 -n1 xmllint --noout

lint-model:
	@for MODEL in $$(find ${.CURDIR}/src/opnsense/mvc/app/models -depth 3 \
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
	done

lint-acl:
	@${.CURDIR}/Scripts/dashboard-acl.sh

SCRIPTDIRS!=	find ${.CURDIR}/src/opnsense/scripts -type d -depth 1

lint-exec:
.for DIR in ${.CURDIR}/src/etc/rc.d ${.CURDIR}/src/etc/rc.syshook.d ${SCRIPTDIRS}
.if exists(${DIR})
	@find ${DIR} -path '**/htdocs_default' -prune -o -type f \
	    ! -name "*.xml" ! -name "*.csv" ! -name "*.sql" -print0 | \
	    xargs -0 -t -n1 test -x || \
	    (echo "Missing executable permission in ${DIR}"; exit 1)
.endif
.endfor

LINTBIN?=	${.CURDIR}/contrib/parallel-lint/parallel-lint

lint-php:
	@${LINTBIN} src

lint: plist-check lint-shell lint-xml lint-model lint-acl lint-exec lint-php

