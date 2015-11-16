all:

TREES_=${TREES}
ROOT_=${ROOT}

.for TARGET in _ ${EXTRA:C/.*/_&/g}

install${TARGET}: force
.for TREE in ${TREES${TARGET}}
	@mkdir -p ${DESTDIR}${ROOT${TARGET}}
	REALTARGET=/$$(dirname ${TREE}); \
	cp -vr ${TREE} ${DESTDIR}${ROOT${TARGET}}$${REALTARGET}
	@(cd ${TREE}; find * -type f) | while read FILE; do \
		if [ $${FILE%%.in} != $${FILE} ]; then \
			sed -i '' \
			    -e "s=%%CORE_PACKAGESITE%%=${CORE_PACKAGESITE}=g" \
			    -e "s=%%CORE_REPOSITORY%%=${CORE_REPOSITORY}=g" \
			    ${DESTDIR}${ROOT${TARGET}}/${TREE}/$${FILE}; \
			mv -v ${DESTDIR}${ROOT${TARGET}}/${TREE}/$${FILE} \
			    ${DESTDIR}${ROOT${TARGET}}/${TREE}/$${FILE%%.in}; \
		fi \
	done
.endfor

plist${TARGET}: force
.for TREE in ${TREES${TARGET}}
	@(cd ${TREE}; find * -type f) | while read FILE; do \
		FILE="$${FILE%%.in}"; PREFIX=""; \
		if [ $${FILE%%.sample} != $${FILE} ]; then \
			PREFIX="@sample "; \
		fi; \
		echo "$${PREFIX}${ROOT${TARGET}}/${TREE}/$${FILE}"; \
	done
.endfor

.endfor

install: install_
plist: plist_
force:

.PHONY: force
