# Copyright (c) 2025-2026 Franco Fichtner <franco@opnsense.org>
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

STYLEDIRS=	${.CURDIR}/src/etc/inc ${.CURDIR}/src/opnsense
PYCODESTYLEBIN=	/usr/local/bin/pycodestyle-${CORE_PYTHON_DOT}
PHPCSBIN=	/usr/local/bin/phpcs

style-python:
.if exists(${PHPCSBIN})
	@${PYCODESTYLEBIN} --ignore=E501 ${.CURDIR}/src || true
.endif

style-php: clean-mfcdir
.if exists(${PHPCSBIN})
.for DIR in ${STYLEDIRS}
.if exists(${DIR})
	@(${PHPCSBIN} --standard=${COREREFDIR}/ruleset.xml ${DIR} || true) >> ${MFCDIR}/.style.out
.endif
.endfor
	@if [ -f ${MFCDIR}/.style.out ]; then \
		echo -n "Total number of style warnings: "; \
		grep '| WARNING' ${MFCDIR}/.style.out | wc -l; \
		echo -n "Total number of style errors:   "; \
		grep '| ERROR' ${MFCDIR}/.style.out | wc -l; \
		cat ${MFCDIR}/.style.out | ${PAGER}; \
		rm ${MFCDIR}/.style.out; \
	fi
.endif

style: style-python style-php
