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

ARGS=	diff feed mfc mlog slog tag vim
VIM!=	which vim || echo false

# handle argument expansion for required targets
.for TARGET in ${.TARGETS}
_TARGET=		${TARGET:C/\-.*//}
.if ${_TARGET} != ${TARGET}
.for ARGUMENT in ${ARGS}
.if ${_TARGET} == ${ARGUMENT}
${_TARGET}_ARGS+=	${TARGET:C/^[^\-]*(\-|\$)//:S/,/ /g}
${TARGET}: ${_TARGET}
.endif
.endfor
${_TARGET}_ARG=		${${_TARGET}_ARGS:[0]}
.endif
.endfor

diff_ARGS?=	${.CURDIR}
mlog_ARGS?=	${.CURDIR}
slog_ARGS?=	${.CURDIR}

ensure-stable:
	@if ! ${GIT} show-ref --verify --quiet refs/heads/${CORE_STABLE}; then \
		${GIT} update-ref refs/heads/${CORE_STABLE} refs/remotes/origin/${CORE_STABLE}; \
		${GIT} config branch.${CORE_STABLE}.merge refs/heads/${CORE_STABLE}; \
		${GIT} config branch.${CORE_STABLE}.remote origin; \
	fi

.if exists(${.CURDIR}/Private)
GIT_PRIVATE=	':!Private/'
.endif

diff: ensure-stable
	@if [ "$$(${GIT} tag -l | grep -cx '${diff_ARGS:[1]}')" = "1" ]; then \
		${GIT} diff --stat -p ${diff_ARGS:[1]} ${GIT_PRIVATE}; \
	else \
		${GIT} diff --stat -p ${CORE_STABLE} ${diff_ARGS} ${GIT_PRIVATE}; \
	fi

tag: ensure-stable
	@${GIT} tag -a -m "stable release" "${tag_ARGS:[1]}" ${CORE_STABLE}

feed: ensure-stable
	@FEED="${feed_ARGS:[1]}"; \
	    if [ -z "$${FEED}" ]; then FEED=$$(${GITVERSION} ${CORE_STABLE} | awk '{print $$1}'); fi; \
	    ${GIT} log --stat -p --reverse ${CORE_STABLE}...$${FEED}~1 ${.CURDIR}

mfc: ensure-stable clean-mfcdir
.for MFC in ${mfc_ARGS}
.if exists(${MFC})
	@cp -r ${MFC} ${MFCDIR}
	@${GIT} checkout ${CORE_STABLE}
	@rm -rf ${MFC}
	@mkdir -p $$(dirname ${MFC})
	@mv ${MFCDIR}/$$(basename ${MFC}) ${MFC}
	@${GIT} add -f .
	@if ! ${GIT} diff --quiet HEAD; then \
		${GIT} commit -m "${MFC}: sync with ${CORE_MAIN}"; \
	fi
.else
	@${GIT} checkout ${CORE_STABLE}
	@if ! ${GIT} cherry-pick -x ${MFC}; then \
		${GIT} cherry-pick --abort; \
	fi
.endif
	@${GIT} checkout ${CORE_MAIN}
.endfor

stable:
	@${GIT} checkout ${CORE_STABLE}

${CORE_MAINS}:
	@${GIT} checkout ${CORE_MAIN}

rebase:
	@${GIT} checkout ${CORE_STABLE}
	@${GIT} rebase -i
	@${GIT} checkout ${CORE_MAIN}

reset:
	@${GIT} checkout ${CORE_STABLE}
	@${GIT} reset --hard HEAD~1
	@${GIT} checkout ${CORE_MAIN}

mlog:
	@${GIT} log --stat -p ${CORE_MAIN} ${mlog_ARGS}

slog: ensure-stable
	@${GIT} log --stat -p ${CORE_STABLE} ${slog_ARGS}

TO_PULL:=	# blank
.if "${CORE_STABLE}" == "stable/${CORE_ABI}"
.for __CORE_ABI in ${CORE_ABIS}
TO_PULL+=	stable/${__CORE_ABI}
.endfor
.else
TO_PULL+=	${CORE_STABLE}
.endif

pull:
.for _TO_PULL in ${TO_PULL}
	@${GIT} checkout ${_TO_PULL}
	@${GIT} pull
.endfor
	@${GIT} checkout ${CORE_MAIN}

push:
	@${GIT} checkout ${CORE_STABLE}
	@${GIT} push
	@${GIT} checkout ${CORE_MAIN}

checkout:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@${GIT} reset -q ${.CURDIR}/src && \
	    ${GIT} checkout -f ${.CURDIR}/src && \
	    ${GIT} clean -xdqf ${.CURDIR}/src
.endif
.endfor

vim:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@FOUND="$$(find ${.CURDIR}/src -type f -name "${vim_ARG}" | head -n 1)"; \
	    if [ -n "$${FOUND}" ]; then \
		${VIM} "$${FOUND}"; \
		${PHPBIN} -l "$${FOUND}" > /dev/null; \
	    else exit 1; fi
.endif
.endfor
