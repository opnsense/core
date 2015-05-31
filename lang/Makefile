all:

XGETTEXT=	xgettext -L PHP --from-code=UTF-8 -F --strict --debug
MSGFMT=		msgfmt --strict

LOCALEDIR=	/usr/local/share/locale/%%LANG%%/LC_MESSAGES

LANGUAGES=	zh_CN ja_JP
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
	# XXX bring this back
	#${.CURDIR}/../scripts/translate/collect.py
	cd ${.CURDIR}/.. && find src | \
	    xargs ${XGETTEXT} -j -o ${.CURDIR}/${TEMPLATE}.pot

install: ${LANGUAGES}
template: ${TEMPLATE}

.PHONY: ${LANGUAGES} ${TEMPLATE}
