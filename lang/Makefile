all:

XGETTEXT=	xgettext -L PHP --from-code=UTF-8 -F --strict --debug
MSGFMT=		msgfmt --strict

LOCALEDIR=	/usr/local/share/locale/%%LANG%%/LC_MESSAGES

LANGUAGES=	de_DE es_CO ja_JP zh_CN
TEMPLATE=	en_US

.for LANG in ${LANGUAGES}
${LANG}DIR=	${LOCALEDIR:S/%%LANG%%/${LANG}/g}
${LANG}:
	@mkdir -p ${DESTDIR}${${LANG}DIR}
	@${MSGFMT} -o ${DESTDIR}${${LANG}DIR}/OPNsense.mo ${LANG}.po
	@echo ${${LANG}DIR}/OPNsense.mo
.endfor

${TEMPLATE}:
	@: > ${TEMPLATE}.pot
	cd ${.CURDIR}/.. && find src lang/dynamic/helpers | \
	    xargs ${XGETTEXT} -j -o ${.CURDIR}/${TEMPLATE}.pot

install: ${LANGUAGES}
template: ${TEMPLATE}

dynamic:
	@${.CURDIR}/dynamic/collect.py ${.CURDIR}/..

.PHONY: ${LANGUAGES} ${TEMPLATE} dynamic
