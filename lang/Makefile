all:

_XGETTEXT!=	which xgettext || echo true
XGETTEXT=	${_XGETTEXT} -L PHP --from-code=UTF-8 -F --strict --debug
_MSGFMT!=	which msgfmt || echo true
MSGFMT=		${_MSGFMT} --strict
_MSGMERGE!=	which msgmerge || echo true
MSGMERGE=	${_MSGMERGE} -U -N --backup=off

LOCALEDIR=	/usr/local/share/locale/%%LANG%%/LC_MESSAGES

# stable
LANGUAGES=	de_DE ja_JP zh_CN
# devel
LANGUAGES+=	es_CO fr_FR mn_MN

TEMPLATE=	en_US
INSTALL=
MERGE=
PLIST=

.for LANG in ${LANGUAGES}
${LANG}DIR=	${LOCALEDIR:S/%%LANG%%/${LANG}/g}
install-${LANG}:
	@mkdir -p ${DESTDIR}${${LANG}DIR}
	${MSGFMT} -o ${DESTDIR}${${LANG}DIR}/OPNsense.mo ${LANG}.po

plist-${LANG}:
	@echo ${${LANG}DIR}/OPNsense.mo

merge-${LANG}:
	${MSGMERGE} ${LANG}.po ${TEMPLATE}.pot

INSTALL+=	install-${LANG}
PLIST+=		plist-${LANG}
MERGE+=		merge-${LANG}
.endfor

${TEMPLATE}:
	@: > ${TEMPLATE}.pot
	cd ${.CURDIR}/.. && find src lang/dynamic/helpers | \
	    xargs ${XGETTEXT} -j -o ${.CURDIR}/${TEMPLATE}.pot

template: ${TEMPLATE}
install: ${INSTALL}
plist: ${PLIST}
merge: ${MERGE}

dynamic:
	@${.CURDIR}/dynamic/collect.py ${.CURDIR}/..

.PHONY: ${INSTALL} ${PLIST} ${MERGE} ${TEMPLATE} dynamic
