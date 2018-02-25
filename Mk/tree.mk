# Copyright (c) 2015-2017 Franco Fichtner <franco@opnsense.org>
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

.include "defaults.mk"

IGNORES_DEFAULT=*.pyc .gitignore
IGNORES+=	${IGNORES_DEFAULT}

.for IGNORE in ${IGNORES}
_IGNORES+=	! -name "${IGNORE}"
.endfor

.for TARGET in ${TREES} ${EXTRAS}

.if "${TREES_${TARGET}}" == ""
TREES_${TARGET}=${TARGET}
.endif

.if "${ROOT_${TARGET}}" == ""
.if "${ROOT}" == ""
.error "No ROOT directory set for target: ${TARGET}"
.endif
ROOT_${TARGET}=${ROOT}
.endif

# fixup root target dir
ROOT_${TARGET}:=${ROOT_${TARGET}:S/^\/$//}

install-${TARGET}:
.for TREE in ${TREES_${TARGET}}
	@REALTARGET=/$$(dirname ${TREE}); \
	mkdir -p ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}; \
	cp -vr ${TREE} ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}
	@(cd ${TREE}; find * -type f ${_IGNORES}) | while read FILE; do \
		if [ "$${FILE%%.in}" != "$${FILE}" ]; then \
			sed -i '' \
			    -e "s=%%CORE_PACKAGESITE%%=${CORE_PACKAGESITE}=g" \
			    -e "s=%%CORE_REPOSITORY%%=${CORE_REPOSITORY}=g" \
			    -e "s=%%CORE_NAME%%=${CORE_NAME}=g" \
			    -e "s=%%CORE_ABI%%=${CORE_ABI}=g" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}"; \
			mv -v "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.in}"; \
		fi; \
		FILE="$${FILE%%.in}"; \
		if [ -n "${NO_SAMPLE}" -a "$${FILE%%.sample}" != "$${FILE}" ]; then \
			mv -v "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.sample}"; \
		fi; \
		if [ "$${FILE%%.shadow}" != "$${FILE}" ]; then \
			if [ -n "${NO_SAMPLE}" ]; then \
				mv -v "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
				    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.shadow}"; \
			else \
				mv "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
				    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.shadow}.sample"; \
			fi; \
		fi; \
	done
.endfor

plist-${TARGET}:
.for TREE in ${TREES_${TARGET}}
	@(cd ${TREE}; find * -type f ${_IGNORES} -o -type l) | while read FILE; do \
		FILE="$${FILE%%.in}"; PREFIX=""; \
		if [ -z "${NO_SAMPLE}" -a "$${FILE%%.sample}" != "$${FILE}" ]; then \
			PREFIX="@sample "; \
		fi; \
		if [ -z "${NO_SAMPLE}" -a "$${FILE%%.shadow}" != "$${FILE}" ]; then \
			FILE="$${FILE%%.shadow}.sample"; \
			PREFIX="@shadow "; \
		fi; \
		if [ -n "${NO_SAMPLE}" ]; then \
			FILE="$${FILE%%.sample}"; \
			FILE="$${FILE%%.shadow}"; \
		fi; \
		echo "$${PREFIX}${ROOT_${TARGET}}/${TREE}/$${FILE}"; \
	done
.endfor

.endfor

.for TARGET in ${TREES}
install: install-${TARGET}
plist: plist-${TARGET}
.endfor
