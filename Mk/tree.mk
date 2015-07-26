all:

install:
.for TREE in ${TREES}
	@mkdir -p ${DESTDIR}${ROOT}
	@cp -vr ${TREE} ${DESTDIR}${ROOT}
.endfor

plist:
.for TREE in ${TREES}
	@(cd ${TREE}; find * -type f) | \
	    xargs -n1 printf "${ROOT}/${TREE}/%s\n"
.endfor

.PHONY: install plist
