all:

.for TARGET in ${TREES} ${EXTRAS}

.if "${TREES_${TARGET}}" == ""
TREES_${TARGET}=${TARGET}
.endif

.if "${ROOT_${TARGET}}" == ""
ROOT_${TARGET}=${ROOT}
.endif

# fixup root target dir
ROOT_${TARGET}:=${ROOT_${TARGET}:S/^\/$//}

install-${TARGET}: force
.for TREE in ${TREES_${TARGET}}
	@REALTARGET=/$$(dirname ${TREE}); \
	mkdir -p ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}; \
	cp -vr ${TREE} ${DESTDIR}${ROOT_${TARGET}}$${REALTARGET}
	@(cd ${TREE}; find * -type f ! -name "*.pyc") | while read FILE; do \
		if [ "$${FILE%%.in}" != "$${FILE}" ]; then \
			sed -i '' \
			    -e "s=%%CORE_PACKAGESITE%%=${CORE_PACKAGESITE}=g" \
			    -e "s=%%CORE_REPOSITORY%%=${CORE_REPOSITORY}=g" \
			    -e "s=%%CORE_NAME%%=${CORE_NAME}=g" \
			    -e "s=%%CORE_ABI%%=${CORE_ABI}=g" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}"; \
			mv -v "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.in}"; \
		fi; \
		FILE="$${FILE%%.in}"; \
		if [ -n "${NO_SAMPLE}" -a "$${FILE%%.sample}" != "$${FILE}" ]; then \
			mv -v "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE}" \
			    "${DESTDIR}${ROOT_${TARGET}}/${TREE}/$${FILE%%.sample}"; \
		fi; \
	done
.endfor

plist-${TARGET}: force
.for TREE in ${TREES_${TARGET}}
	@(cd ${TREE}; find * -type f ! -name "*.pyc") | while read FILE; do \
		FILE="$${FILE%%.in}"; PREFIX=""; \
		if [ -z "${NO_SAMPLE}" -a "$${FILE%%.sample}" != "$${FILE}" ]; then \
			PREFIX="@shadow "; \
		fi; \
		if [ -n "${NO_SAMPLE}" ]; then \
			FILE="$${FILE%%.sample}"; \
		fi; \
		echo "$${PREFIX}${ROOT_${TARGET}}/${TREE}/$${FILE}"; \
	done
.endfor

.endfor

.for TARGET in ${TREES}
install: install-${TARGET}
plist: plist-${TARGET}
.endfor

force:

.PHONY: force
