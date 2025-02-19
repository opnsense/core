DynamicAliases
========================

This namespace may contain simple classes which generate aliases that should be merged automatically in Firewall/Aliases.
Each class should have a `collect()` method returning a named array with alias registration info.

An easy example of the expected result can be found in `../static_aliases/core.json`

** Note: make sure actions are "light weight" to prevent excessive api execution times.
