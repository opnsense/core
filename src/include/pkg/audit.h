/*
 * Copyright (c) 2020 Baptiste Daroussin <bapt@FreeBSD.org>
 * Copyright (c) 2014-2016 Vsevolod Stakhov <vsevolod@FreeBSD.org>
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer
 *    in this position and unchanged.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR(S) ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR(S) BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 */

#ifndef _PKG_AUDIT_H
#define _PKG_AUDIT_H

#ifdef __cplusplus
extern "C" {
#endif

#include <time.h>
#include <ucl.h>

#define EQ 1
#define LT 2
#define LTE 3
#define GT 4
#define GTE 5

struct pkg_audit_version {
	char *version;
	int type;
};

struct pkg_audit_versions_range {
	struct pkg_audit_version v1;
	struct pkg_audit_version v2;
	int type;
	struct pkg_audit_versions_range *next;
};

struct pkg_audit_cve {
	char *cvename;
	struct pkg_audit_cve *next;
};

struct pkg_audit_pkgname {
	char *pkgname;
	struct pkg_audit_pkgname *next;
};

struct pkg_audit_reference {
	char *url;
	int type;
	struct pkg_audit_reference *next;
};

struct pkg_audit_ecosystem {
	char *original;
	char *name;
	ucl_object_t *params;
};

struct pkg_audit_package {
	struct pkg_audit_pkgname *names;
	struct pkg_audit_versions_range *versions;
	struct pkg_audit_ecosystem *ecosystem;
	struct pkg_audit_package *next;
};

struct pkg_audit_entry {
	const char *pkgname;
	struct pkg_audit_package *packages;
	struct pkg_audit_pkgname *names;
	struct pkg_audit_versions_range *versions;
	struct pkg_audit_cve *cve;
	struct pkg_audit_reference *references;
	struct tm modified;
	struct tm published;
	struct tm discovery;
	char *url;
	char *desc;
	char *id;
	bool ref;
	struct pkg_audit_entry *next;
};

struct pkg_audit_issue {
	const struct pkg_audit_entry *audit;
	struct pkg_audit_issue *next;
};

struct pkg_audit_issues {
	int count;
	struct pkg_audit_issue *issues;
};

struct pkg_audit_cpe {
	unsigned int version_major;
	unsigned int version_minor;
	char part;
	char *vendor;
	char *product;
	char *version;
	char *update;
	char *edition;
	char *language;
	char *sw_edition;
	char *target_sw;
	char *target_hw;
	char *other;
};

/**
 * Creates new pkg_audit structure
 */
struct pkg_audit * pkg_audit_new(void);

/**
 * Fetch and extract audit file from url `src` to the file `dest`
 * If no update is required then this function returns `EPKG_UPTODATE`
 * @return error code
 */
int pkg_audit_fetch(const char *src, const char *dest);

/**
 * Load audit file into memory
 * @return error code
 */
int pkg_audit_load(struct pkg_audit *audit, const char *fname);

/**
 * Process loaded audit structure.
 * Can and should be executed after cap_enter(3) or another sandboxing call
 * @return error code
 */
int pkg_audit_process(struct pkg_audit *audit);

/**
 * Check whether `pkg` is vulnerable against processed `audit` structure.
 * If a package is vulnerable, then `result` is set to sbuf describing the
 * vulnerability. If `quiet` is true, then this function produces reduced output
 * just returning a name of vulnerable package.
 * It's caller responsibility to free `result` after use
 * @return true and `*result` is set if a package is vulnerable
 */
bool pkg_audit_is_vulnerable(struct pkg_audit *audit, struct pkg *pkg, struct pkg_audit_issues **issues, bool stop_quick);

void pkg_audit_free(struct pkg_audit *audit);
void pkg_audit_issues_free(struct pkg_audit_issues *issues);
#ifdef __cplusplus
}
#endif
#endif
