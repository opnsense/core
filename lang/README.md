OPNsense language translation kit
=================================

The kit requires additional tools in order to properly extract strings
from the source code.  You'll need to run this once locally:

    # pkg install gettext-tools p5-Locale-Maketext-Lexicon python27

Regenerate dynamic strings that can't be found in the template
generation step (XML contents, etc.):

    # make dynamic

Regenerate the translation template using:

    # make template

Merge the latest template changes into the actual translations:

    # make merge

Remove the compiled translation files from the system/chroot:

    # make clean

The build system will automatically pick up all registered languages.
