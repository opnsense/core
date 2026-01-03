Contributing to OPNsense
========================

Thanks for considering a pull request or issue report.  Below are a
few hints and tips in order to make them as effective as possible.

Issue reports
-------------

Issue reports can be bug reports or feature requests.  Make sure to
search the open and closed issues before adding a new one.  It is
often better to join an ongoing discussions on similar open issues
than creating a new one as there may be workarounds or ideas available.

When creating bug reports, please make sure you provide the following:

* The current OPNsense version where the bug first appeared
* The last OPNsense version where the bug did not exist
* The exact URL of the GUI page involved (if any)
* A list of steps to replicate the bug

Issue templates can help with getting this just right.

All issues reported will have to be triaged and prioritised.  As we
are a small team we may not always have the time to implement and help,
but reporting an issue may help others to fill in.

The issue categories are as follows:

* support: community-based help figuring out setup issues or code problems including awaiting triage
* cleanup: cosmetic changes or non-operational bugs (display issues, etc.)
* bug: identified operational bug (core features, etc.)
* feature: behavioural changes, additions as well as missing options
* help wanted: a contributor is missing to carry out the work
* upstream: problem exists in the included third-party software
* incomplete: issue template missing or incomplete

Feature requests that are in line with project goals will eventually
be added to our roadmap:

https://opnsense.org/about/road-map/

Feature requests beyond the scope of OPNsense may still be provided
using the plugin framework:

https://github.com/opnsense/plugins/issues

Stale issues are timed out after 180 days inactivity.  Please
note that this includes non-support issues such as feature requests
that are not picked up by a contributor, which means it is highly
unlikely the feature will be implemented in the first place unless a
pull request is provided along with the issue.

Responding to issues is completely voluntary for all participants.
As a general rule, closed tickets shall and will not be responded to.

And above all: stay kind and open.  :)

Pull requests
-------------

When creating pull request, please heed the following:

* Base your code on the latest `master` branch to avoid manual merges
* Code review by the team may occur to help you shape your proposal
* Test your proposal operationally to catch mistakes and avoid merge delay
* Pull request must adhere to 2-Clause BSD licensing
* Explain the problem and your proposed solution
* If applicable cite the issue(s) number(s) in your pull-request description,
    for example `Fixes: #1234`, `Closes: #1234`, or `Ref: #1234`.
* Read [README.md](./README.md) to learn about the commands to shape your code

Stable release updates
----------------------

After merging a pull-request into the `master` branch a team member may cherry-pick
your work to update the current stable version (branches: `stable/<major>.<minor>`).
