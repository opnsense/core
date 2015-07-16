all:

XGETTEXT=	xgettext -L PHP --from-code=UTF-8 -F --strict --debug
MSGFMT=		msgfmt --strict

LOCALEDIR=	/usr/local/share/locale/%%LANG%%/LC_MESSAGES

LANGUAGES=	de_DE es_CO ja_JP zh_CN
TEMPLATE=	en_US
INSTALL=
PLIST=

.for LANG in ${LANGUAGES}
${LANG}DIR=	${LOCALEDIR:S/%%LANG%%/${LANG}/g}
install-${LANG}:
	@mkdir -p ${DESTDIR}${${LANG}DIR}
	${MSGFMT} -o ${DESTDIR}${${LANG}DIR}/OPNsense.mo ${LANG}.po

plist-${LANG}:
	@echo ${${LANG}DIR}/OPNsense.mo

INSTALL+=	install-${LANG}
PLIST+=		plist-${LANG}
.endfor

${TEMPLATE}:
	@: > ${TEMPLATE}.pot
	cd ${.CURDIR}/.. && find src lang/dynamic/helpers | \
	    xargs ${XGETTEXT} -j -o ${.CURDIR}/${TEMPLATE}.pot

template: ${TEMPLATE}
install: ${INSTALL}
plist: ${PLIST}

dynamic:
	@${.CURDIR}/dynamic/collect.py ${.CURDIR}/..

.PHONY: ${INSTALL} ${PLIST} ${TEMPLATE} dynamic
