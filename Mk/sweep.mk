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

sweep-model:
.for DIR in ${.CURDIR}/src/opnsense/mvc/app/models
.if exists(${DIR})
	@for MODEL in $$(find ${DIR} -depth 3 \
	    -name "*.xml"); do \
		perl -i -pe 's/<multiple>([YyNn])<\/multiple>/<Multiple>$$1<\/Multiple>/g' $${MODEL}; \
		perl -i -pe 's/<required>([YyNn])<\/required>/<Required>$$1<\/Required>/g' $${MODEL}; \
		perl -i -pe 's/<asList>([YyNn])<\/asList>/<AsList>$$1<\/AsList>/g' $${MODEL}; \
		perl -i -pe 's/<default>(.*?)<\/default>/<Default>$$1<\/Default>/g' $${MODEL}; \
		perl -i -pe 's/<mask>(.*?)<\/mask>/<Mask>$$1<\/Mask>/g' $${MODEL}; \
		env XMLLINT_INDENT="    " xmllint --format --output $${MODEL} $${MODEL}; \
		sed -i '' 1d $${MODEL}; \
	done
.endif
.endfor

sweep-php:
.for DIR in ${STYLEDIRS}
.if exists(${DIR})
	phpcbf --standard=${COREREFDIR}/ruleset.xml ${DIR} || true
.endif
.endfor

sweep-whitespace:
.for DIR in ${.CURDIR}/src
.if exists(${DIR})
	find ${DIR} ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.ser" ! -name "*.css.map" -type f -print0 | \
	    xargs -0 -n1 ${COREREFDIR}/Scripts/cleanfile
.endif
.endfor
.for DIR in ${.CURDIR}/Scripts ${.CURDIR}/.github
.if exists(${DIR})
	find ${DIR} -type f -print0 | \
	    xargs -0 -n1 ${COREREFDIR}/Scripts/cleanfile
.endif
.endfor
	find ${.CURDIR} -type f -depth 1 -print0 | \
	    xargs -0 -n1 ${COREREFDIR}/Scripts/cleanfile

sweep: sweep-whitespace sweep-model sweep-php
