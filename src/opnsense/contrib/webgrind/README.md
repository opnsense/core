Webgrind
========
Webgrind is a [Xdebug](http://www.xdebug.org) profiling web frontend in PHP5. It implements a subset of the features of [kcachegrind](http://kcachegrind.sourceforge.net/cgi-bin/show.cgi) and installs in seconds and works on all platforms. For quick'n'dirty optimizations it does the job. Here's a screenshot showing the output from profiling:

[![](http://jokke.dk/media/2008-webgrind/webgrind_small.png)](http://jokke.dk/media/2008-webgrind/webgrind_large.png)

It is possible that a larger number of kcachegrind features will be implemented in the future, bringing webgrind closer to completing one of the [suggested PHP Google Summer of Code 2008](http://wiki.php.net/gsoc/2008#xdebug_profiling_web_frontend). At this point nothing has been planned, though.

Features
--------
  * Super simple, cross platform installation - obviously :)
  * Track time spent in functions by self cost or inclusive cost. Inclusive cost is time inside function + calls to other functions.
  * See if time is spent in internal or user functions.
  * See where any function was called from and which functions it calls.
  * Generate a call graph using [gprof2dot.py](https://github.com/jrfonseca/gprof2dot)

Suggestions for improvements and new features are more than welcome - this is just a start.

Mailing list is available through the [webgrind google group](http://groups.google.com/group/webgrind-general/topics).

Installation
------------
  1. Download webgrind
  2. Unzip package to favourite path accessible by webserver.
  3. Load webgrind in browser and start profiling

See the [Installation Wiki page](https://github.com/jokkedk/webgrind/wiki/Installation) for more

Credits
-------
Webgrind is written by [Joakim Nygård](http://jokke.dk) and [Jacob Oettinger](http://oettinger.dk). It would not have been possible without the great tool that Xdebug is thanks to [Derick Rethans](http://www.derickrethans.nl).
