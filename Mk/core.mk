# Copyright (c) 2015-2020 Franco Fichtner <franco@opnsense.org>
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
	@REALTARGET="/${TREE}"; \
	if [ -z "${ROOT_${TARGET}}" ]; then REALTARGET=/; fi; \
	mkdir -p ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}; \
	tar -C ${TREE} -cf - . | tar -C ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET} -xf -; \
	(cd ${TREE}; find * -type f ${_IGNORES}) | while read FILE; do \
		if [ "$${FILE%%.in}" != "$${FILE}" ]; then \
			sed -i '' ${SED_REPLACE} \
			    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}"; \
			mv "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}" \
			    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE%%.in}"; \
			FILE="$${FILE%%.in}"; \
		fi; \
		if [ "$${FILE%%.link}" != "$${FILE}" ]; then \
			(cd "$$(dirname "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}")"; \
				ln -sfn "$$(cat ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE})" \
				    "$$(basename "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE%%.link}")"); \
			rm "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}"; \
		elif [ "$${FILE%%.sample}" != "$${FILE}" ]; then \
			if [ -n "${NO_SAMPLE}" ]; then \
				mv "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}" \
				    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE%%.sample}"; \
			fi; \
		elif [ "$${FILE%%.shadow}" != "$${FILE}" ]; then \
			if [ -n "${NO_SAMPLE}" ]; then \
				mv "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}" \
				    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE%%.shadow}"; \
			else \
				mv "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}" \
				    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE%%.shadow}.sample"; \
			fi; \
		fi; \
		if [ "${TREE}" = "man" ]; then \
			gzip -cn "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}" > \
			    "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}.gz"; \
			rm "${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}"; \
		fi; \
	done
.endfor

plist-${TARGET}:
.for TREE in ${TREES_${TARGET}}
	@(cd ${TREE}; find * -type f ${_IGNORES} -o -type l) | while read FILE; do \
		if [ -f "${TREE}/$${FILE}.in" ]; then continue; fi; \
		FILE="$${FILE%%.in}"; PREFIX=""; \
		if [ "$${FILE%%.link}" != "$${FILE}" ]; then \
			FILE="$${FILE%%.link}"; \
		elif [ "$${FILE%%.sample}" != "$${FILE}" ]; then \
			if [ -n "${NO_SAMPLE}" ]; then \
				FILE="$${FILE%%.sample}"; \
			else \
				PREFIX="@sample "; \
			fi; \
		elif [ "$${FILE%%.shadow}" != "$${FILE}" ]; then \
			if [ -n "${NO_SAMPLE}" ]; then \
				FILE="$${FILE%%.shadow}"; \
			else \
				FILE="$${FILE%%.shadow}.sample"; \
				PREFIX="@shadow "; \
			fi; \
		fi; \
		if [ "${TREE}" == "man" ]; then \
			FILE="$${FILE}.gz"; \
		fi; \
		REALTARGET="/${TREE}"; \
		if [ -z "${ROOT_${TARGET}}" ]; then REALTARGET=; fi; \
		echo "$${PREFIX}${ROOT_${TARGET}}$${REALTARGET}/$${FILE}"; \
	done
.endfor

.endfor

.for TARGET in ${TREES}
install: install-${TARGET}
plist: plist-${TARGET}
.endfor
