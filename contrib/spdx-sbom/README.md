# SPDX Generator

A small tool for generating **SPDX 3** files for software packages.

## Usage

Provide the package name:

```bash
spdx-generator.py <package-name>
```

Example:

```bash
spdx-generator.py opnsense
```

The tool generates an SPDX formatted software bill of materials for the package offered as parameter.


# TODO

* normalize licenseExpression to spdx standards
* implement creationInfo tag properly

