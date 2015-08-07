all:

install:
.for TREE in ${TREES}
	@mkdir -p ${DESTDIR}${ROOT}
	@cp -vr ${TREE} ${DESTDIR}${ROOT}
	@(cd ${TREE}; find * -type f) | while read FILE; do \
		if [ $${FILE%%.in} != $${FILE} ]; then \
			sed -i '' \
			    -e "s=%%CORE_PACKAGESITE%%=${CORE_PACKAGESITE}=g" \
			    -e "s=%%CORE_REPOSITORY%%=${CORE_REPOSITORY}=g" \
			    ${DESTDIR}${ROOT}/${TREE}/$${FILE}; \
			mv -v ${DESTDIR}${ROOT}/${TREE}/$${FILE} \
			    ${DESTDIR}${ROOT}/${TREE}/$${FILE%%.in}; \
		fi \
	done
.endfor

plist:
.for TREE in ${TREES}
	@(cd ${TREE}; find * -type f) | while read FILE; do \
		FILE="$${FILE%%.in}"; PREFIX=""; \
		if [ $${FILE%%.sample} != $${FILE} ]; then \
			PREFIX="@sample "; \
		fi; \
		echo "$${PREFIX}${ROOT}/${TREE}/$${FILE}"; \
	done
.endfor

.PHONY: install plist
