OPNsense GUI and system management
==================================

The OPNsense project invites developers to start contributing to
the code base.  For your own purposes or – even better – to join us in
creating the best open source firewall available.

The build process has been designed to make it easy for anyone to
build and write code.  The main outline of the new codebase is
available at:

https://docs.opnsense.org/development/architecture.html

Our aim is to gradually evolve to a new codebase instead of using a
big bang approach into something new.

Build tools
===========

To create working software like OPNsense you need the sources and the
tools to build it.  The build tools for OPNsense are freely available.

Notes on how to build OPNsense can be found in the tools repository:

https://github.com/opnsense/tools

Contribute
==========

You can contribute to the project in many ways, e.g. testing
functionality, sending in bug reports or creating pull requests
directly via GitHub.  Any help is always very welcome!

License
=======

OPNsense is and will always be available under the 2-Clause BSD license:

https://opensource.org/licenses/BSD-2-Clause

Every contribution made to the project must be licensed under the
same conditions in order to keep OPNsense truly free and accessible
for everybody.

Makefile targets
================

The repository offers a couple of targets that either tie into
tools.git build processes or are aimed at fast development.

make package
------------

A package of the current state of the repository can be created using
this target.  It may require several packages to be installed.  The
target will try to assist in case of failure, e.g. when a missing file
needs to be fetched from an external location.

Several OPTIONS exist to customise the package, e.g.:

* CORE_DEPENDS: a list of required dependencies for the package
* CORE_DEPENDS_ARCH: a list of special <ARCH>-required packages
* CORE_ORIGIN: sets a HardenedBSD compatible package/ports origin
* CORE_FLAVOUR: can be set to "OpenSSL" or "LibreSSL"
* CORE_COMMENT: a short description of the package
* CORE_MAINTAINER: email of the package maintainer
* CORE_WWW: web url of the package
* CORE_NAME: sets a package name

Options are passed in the following form:

    # make package CORE_NAME=my_new_name

In general, options are either set to sane defaults or automatically
detected at runtime.

make update
-----------

Update will pull the latest commits from the current branch from the
upstream repository.

make upgrade
------------

Upgrade will run the package build and replace the currently installed
package in the system.

make collect
------------

Fetch changes from the running system for all known files.

make lint
---------

Run serveral syntax checks on the repository.  This is recommended
before issuing a pull request on GitHub.

make style
----------

Run the PSR2 and PEP8 style checks on MVC PHP code and Python,
respectively.

make sweep
----------

Run Linux Kernel cleanfile whitespace sanitiser on all files.
