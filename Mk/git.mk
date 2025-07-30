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

ARGS=	diff feed mlog slog mfc

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

_feed_ARGS!=	${GITVERSION} ${CORE_STABLE}
feed_ARGS?=	${_feed_ARGS}
diff_ARGS?=	${.CURDIR}
mlog_ARGS?=	${.CURDIR}
slog_ARGS?=	${.CURDIR}

ensure-stable:
	@if ! git show-ref --verify --quiet refs/heads/${CORE_STABLE}; then \
		git update-ref refs/heads/${CORE_STABLE} refs/remotes/origin/${CORE_STABLE}; \
		git config branch.${CORE_STABLE}.merge refs/heads/${CORE_STABLE}; \
		git config branch.${CORE_STABLE}.remote origin; \
	fi

diff: ensure-stable
	@if [ "$$(git tag -l | grep -cx '${diff_ARGS:[1]}')" = "1" ]; then \
		git diff --stat -p ${diff_ARGS:[1]}; \
	else \
		git diff --stat -p ${CORE_STABLE} ${diff_ARGS}; \
	fi

feed: ensure-stable
	@git log --stat -p --reverse ${CORE_STABLE}...${feed_ARGS:[1]}~1 ${.CURDIR}

mfc: ensure-stable clean-mfcdir
.for MFC in ${mfc_ARGS}
.if exists(${MFC})
	@cp -r ${MFC} ${MFCDIR}
	@git checkout ${CORE_STABLE}
	@rm -rf ${MFC}
	@mkdir -p $$(dirname ${MFC})
	@mv ${MFCDIR}/$$(basename ${MFC}) ${MFC}
	@git add -f .
	@if ! git diff --quiet HEAD; then \
		git commit -m "${MFC}: sync with ${CORE_MAIN}"; \
	fi
.else
	@git checkout ${CORE_STABLE}
	@if ! git cherry-pick -x ${MFC}; then \
		git cherry-pick --abort; \
	fi
.endif
	@git checkout ${CORE_MAIN}
.endfor

stable:
	@git checkout ${CORE_STABLE}

${CORE_MAINS}:
	@git checkout ${CORE_MAIN}

rebase:
	@git checkout ${CORE_STABLE}
	@git rebase -i
	@git checkout ${CORE_MAIN}

reset:
	@git checkout ${CORE_STABLE}
	@git reset --hard HEAD~1
	@git checkout ${CORE_MAIN}

mlog:
	@git log --stat -p ${CORE_MAIN} ${mlog_ARGS}

slog: ensure-stable
	@git log --stat -p ${CORE_STABLE} ${slog_ARGS}

pull:
	@git checkout ${CORE_STABLE}
	@git pull
	@git checkout ${CORE_MAIN}

push:
	@git checkout ${CORE_STABLE}
	@git push
	@git checkout ${CORE_MAIN}

checkout:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	@git reset -q ${.CURDIR}/src && \
	    git checkout -f ${.CURDIR}/src && \
	    git clean -xdqf ${.CURDIR}/src
.endif
.endfor
