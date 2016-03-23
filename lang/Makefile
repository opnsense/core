XGETTEXT=	xgettext -L PHP --from-code=UTF-8 -F --strict --debug
XGETTEXT_PL=	xgettext.pl -P Locale::Maketext::Extract::Plugin::Volt \
		-u -w -W
MSGMERGE=	msgmerge -U -N --backup=off
MSGFMT=		msgfmt --strict

PERL_DIR=	/usr/local/lib/perl5/site_perl
PERL_NAME=	Locale/Maketext/Extract/Plugin

LOCALEDIR=	/usr/local/share/locale/%%LANG%%/LC_MESSAGES

# stable
LANGUAGES=	de_DE fr_FR ja_JP zh_CN
# devel
LANGUAGES+=	es_CO mn_MN nl_NL ru_RU

TEMPLATE=	en_US
INSTALL=
MERGE=
PLIST=

PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

.for LANG in ${LANGUAGES}
${LANG}DIR=	${LOCALEDIR:S/%%LANG%%/${LANG}/g}
install-${LANG}:
	@mkdir -p ${DESTDIR}${${LANG}DIR}
	${MSGFMT} -o ${DESTDIR}${${LANG}DIR}/OPNsense.mo ${LANG}.po

clean-${LANG}:
	@rm -f ${DESTDIR}${${LANG}DIR}/OPNsense.mo

plist-${LANG}:
	@echo ${${LANG}DIR}/OPNsense.mo

merge-${LANG}:
	${MSGMERGE} ${LANG}.po ${TEMPLATE}.pot
	# strip stale translations
	sed -i '' -e '/^#~.*/d' ${LANG}.po

INSTALL+=	install-${LANG}
CLEAN+=		clean-${LANG}
PLIST+=		plist-${LANG}
MERGE+=		merge-${LANG}
.endfor

${TEMPLATE}:
	@cp ${.CURDIR}/Volt.pm ${PERL_DIR}/${PERL_NAME}/
	@: > ${TEMPLATE}.pot
	cd ${.CURDIR}/.. && \
	    ${XGETTEXT_PL} -D src -p ${.CURDIR} -o ${TEMPLATE}.pot
	cd ${.CURDIR}/.. && find src lang/dynamic/helpers | \
	    xargs ${XGETTEXT} -j -o ${.CURDIR}/${TEMPLATE}.pot

template: ${TEMPLATE}
install: ${INSTALL}
clean: ${CLEAN}
plist: ${PLIST}
merge: ${MERGE}

dynamic:
	@${.CURDIR}/dynamic/collect.py ${.CURDIR}/..

.PHONY: ${INSTALL} ${PLIST} ${MERGE} ${TEMPLATE} dynamic
