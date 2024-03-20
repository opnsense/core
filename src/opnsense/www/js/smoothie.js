;(function(exports) {

/**
 * @license
 * MIT License:
 *
 * Copyright (c) 2010-2013, Joe Walnes
 *               2013-2018, Drew Noakes
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Smoothie Charts - http://smoothiecharts.org/
 * (c) 2010-2013, Joe Walnes
 *     2013-2018, Drew Noakes
 *
 * v1.0: Main charting library, by Joe Walnes
 * v1.1: Auto scaling of axis, by Neil Dunn
 * v1.2: fps (frames per second) option, by Mathias Petterson
 * v1.3: Fix for divide by zero, by Paul Nikitochkin
 * v1.4: Set minimum, top-scale padding, remove timeseries, add optional timer to reset bounds, by Kelley Reynolds
 * v1.5: Set default frames per second to 50... smoother.
 *       .start(), .stop() methods for conserving CPU, by Dmitry Vyal
 *       options.interpolation = 'bezier' or 'line', by Dmitry Vyal
 *       options.maxValue to fix scale, by Dmitry Vyal
 * v1.6: minValue/maxValue will always get converted to floats, by Przemek Matylla
 * v1.7: options.grid.fillStyle may be a transparent color, by Dmitry A. Shashkin
 *       Smooth rescaling, by Kostas Michalopoulos
 * v1.8: Set max length to customize number of live points in the dataset with options.maxDataSetLength, by Krishna Narni
 * v1.9: Display timestamps along the bottom, by Nick and Stev-io
 *       (https://groups.google.com/forum/?fromgroups#!topic/smoothie-charts/-Ywse8FCpKI%5B1-25%5D)
 *       Refactored by Krishna Narni, to support timestamp formatting function
 * v1.10: Switch to requestAnimationFrame, removed the now obsoleted options.fps, by Gergely Imreh
 * v1.11: options.grid.sharpLines option added, by @drewnoakes
 *        Addressed warning seen in Firefox when seriesOption.fillStyle undefined, by @drewnoakes
 * v1.12: Support for horizontalLines added, by @drewnoakes
 *        Support for yRangeFunction callback added, by @drewnoakes
 * v1.13: Fixed typo (#32), by @alnikitich
 * v1.14: Timer cleared when last TimeSeries removed (#23), by @davidgaleano
 *        Fixed diagonal line on chart at start/end of data stream, by @drewnoakes
 * v1.15: Support for npm package (#18), by @dominictarr
 *        Fixed broken removeTimeSeries function (#24) by @davidgaleano
 *        Minor performance and tidying, by @drewnoakes
 * v1.16: Bug fix introduced in v1.14 relating to timer creation/clearance (#23), by @drewnoakes
 *        TimeSeries.append now deals with out-of-order timestamps, and can merge duplicates, by @zacwitte (#12)
 *        Documentation and some local variable renaming for clarity, by @drewnoakes
 * v1.17: Allow control over font size (#10), by @drewnoakes
 *        Timestamp text won't overlap, by @drewnoakes
 * v1.18: Allow control of max/min label precision, by @drewnoakes
 *        Added 'borderVisible' chart option, by @drewnoakes
 *        Allow drawing series with fill but no stroke (line), by @drewnoakes
 * v1.19: Avoid unnecessary repaints, and fixed flicker in old browsers having multiple charts in document (#40), by @asbai
 * v1.20: Add SmoothieChart.getTimeSeriesOptions and SmoothieChart.bringToFront functions, by @drewnoakes
 * v1.21: Add 'step' interpolation mode, by @drewnoakes
 * v1.22: Add support for different pixel ratios. Also add optional y limit formatters, by @copacetic
 * v1.23: Fix bug introduced in v1.22 (#44), by @drewnoakes
 * v1.24: Fix bug introduced in v1.23, re-adding parseFloat to y-axis formatter defaults, by @siggy_sf
 * v1.25: Fix bug seen when adding a data point to TimeSeries which is older than the current data, by @Nking92
 *        Draw time labels on top of series, by @comolosabia
 *        Add TimeSeries.clear function, by @drewnoakes
 * v1.26: Add support for resizing on high device pixel ratio screens, by @copacetic
 * v1.27: Fix bug introduced in v1.26 for non whole number devicePixelRatio values, by @zmbush
 * v1.28: Add 'minValueScale' option, by @megawac
 *        Fix 'labelPos' for different size of 'minValueString' 'maxValueString', by @henryn
 * v1.29: Support responsive sizing, by @drewnoakes
 * v1.29.1: Include types in package, and make property optional, by @TrentHouliston
 * v1.30: Fix inverted logic in devicePixelRatio support, by @scanlime
 * v1.31: Support tooltips, by @Sly1024 and @drewnoakes
 * v1.32: Support frame rate limit, by @dpuyosa
 * v1.33: Use Date static method instead of instance, by @nnnoel
 *        Fix bug with tooltips when multiple charts on a page, by @jpmbiz70
 * v1.34: Add disabled option to TimeSeries, by @TechGuard (#91)
 *        Add nonRealtimeData option, by @annazhelt (#92, #93)
 *        Add showIntermediateLabels option, by @annazhelt (#94)
 *        Add displayDataFromPercentile option, by @annazhelt (#95)
 *        Fix bug when hiding tooltip element, by @ralphwetzel (#96)
 *        Support intermediate y-axis labels, by @beikeland (#99)
 * v1.35: Fix issue with responsive mode at high DPI, by @drewnoakes (#101)
 * v1.36: Add tooltipLabel to ITimeSeriesPresentationOptions.
 *        If tooltipLabel is present, tooltipLabel displays inside tooltip
 *        next to value, by @jackdesert (#102)
 *        Fix bug rendering issue in series fill when using scroll backwards, by @olssonfredrik
 *        Add title option, by @mesca
 *        Fix data drop stoppage by rejecting NaNs in append(), by @timdrysdale
 *        Allow setting interpolation per time series, by @WofWca (#123)
 *        Fix chart constantly jumping in 1-2 pixel steps, by @WofWca (#131)
 *        Fix a memory leak appearing when some `timeSeries.disabled === true`, by @WofWca (#132)
 *        Fix: make all lines sharp, remove the `grid.sharpLines` option by @WofWca (#134)
 *        Improve performance, by @WofWca (#135)
 *        Fix `this.delay` not being respected with `nonRealtimeData: true`, by @WofWca (#137)
 *        Fix series fill & stroke being inconsistent for last data time < render time, by @WofWca (#138)
 * v1.36.1: Fix a potential XSS when `tooltipLabel` or `strokeStyle` are controlled by users, by @WofWca
 * v1.36.2: fix: 1px lines jumping 1px left and right at rational `millisPerPixel`, by @WofWca
 *          perf: improve `render()` performane a bit, by @WofWca
 * v1.37: Add `fillToBottom` option to fill timeSeries to 0 instead of to the bottom of the canvas, by @socketpair & @WofWca (#140)
 */

  // Date.now polyfill
  Date.now = Date.now || function() { return new Date().getTime(); };

  var Util = {
    extend: function() {
      arguments[0] = arguments[0] || {};
      for (var i = 1; i < arguments.length; i++)
      {
        for (var key in arguments[i])
        {
          if (arguments[i].hasOwnProperty(key))
          {
            if (typeof(arguments[i][key]) === 'object') {
              if (arguments[i][key] instanceof Array) {
                arguments[0][key] = arguments[i][key];
              } else {
                arguments[0][key] = Util.extend(arguments[0][key], arguments[i][key]);
              }
            } else {
              arguments[0][key] = arguments[i][key];
            }
          }
        }
      }
      return arguments[0];
    },
    binarySearch: function(data, value) {
      var low = 0,
          high = data.length;
      while (low < high) {
        var mid = (low + high) >> 1;
        if (value < data[mid][0])
          high = mid;
        else
          low = mid + 1;
      }
      return low;
    },
    // So lines (especially vertical and horizontal) look a) consistent along their length and b) sharp.
    pixelSnap: function(position, lineWidth) {
      if (lineWidth % 2 === 0) {
        // Closest pixel edge.
        return Math.round(position);
      } else {
        // Closest pixel center.
        return Math.floor(position) + 0.5;
      }
    },
  };

  /**
   * Initialises a new <code>TimeSeries</code> with optional data options.
   *
   * Options are of the form (defaults shown):
   *
   * <pre>
   * {
   *   resetBounds: true,        // enables/disables automatic scaling of the y-axis
   *   resetBoundsInterval: 3000 // the period between scaling calculations, in millis
   * }
   * </pre>
   *
   * Presentation options for TimeSeries are specified as an argument to <code>SmoothieChart.addTimeSeries</code>.
   *
   * @constructor
   */
  function TimeSeries(options) {
    this.options = Util.extend({}, TimeSeries.defaultOptions, options);
    this.disabled = false;
    this.clear();
  }

  TimeSeries.defaultOptions = {
    resetBoundsInterval: 3000,
    resetBounds: true
  };

  /**
   * Clears all data and state from this TimeSeries object.
   */
  TimeSeries.prototype.clear = function() {
    this.data = [];
    this.maxValue = Number.NaN; // The maximum value ever seen in this TimeSeries.
    this.minValue = Number.NaN; // The minimum value ever seen in this TimeSeries.
  };

  /**
   * Recalculate the min/max values for this <code>TimeSeries</code> object.
   *
   * This causes the graph to scale itself in the y-axis.
   */
  TimeSeries.prototype.resetBounds = function() {
    if (this.data.length) {
      // Walk through all data points, finding the min/max value
      this.maxValue = this.data[0][1];
      this.minValue = this.data[0][1];
      for (var i = 1; i < this.data.length; i++) {
        var value = this.data[i][1];
        if (value > this.maxValue) {
          this.maxValue = value;
        }
        if (value < this.minValue) {
          this.minValue = value;
        }
      }
    } else {
      // No data exists, so set min/max to NaN
      this.maxValue = Number.NaN;
      this.minValue = Number.NaN;
    }
  };

  /**
   * Adds a new data point to the <code>TimeSeries</code>, preserving chronological order.
   *
   * @param timestamp the position, in time, of this data point
   * @param value the value of this data point
   * @param sumRepeatedTimeStampValues if <code>timestamp</code> has an exact match in the series, this flag controls
   * whether it is replaced, or the values summed (defaults to false.)
   */
  TimeSeries.prototype.append = function(timestamp, value, sumRepeatedTimeStampValues) {
	// Reject NaN
	if (isNaN(timestamp) || isNaN(value)){
		return
	}  

    var lastI = this.data.length - 1;
    if (lastI >= 0) {
      // Rewind until we find the place for the new data
      var i = lastI;
      while (true) {
        var iThData = this.data[i];
        if (timestamp >= iThData[0]) {
          if (timestamp === iThData[0]) {
            // Update existing values in the array
            if (sumRepeatedTimeStampValues) {
              // Sum this value into the existing 'bucket'
              iThData[1] += value;
              value = iThData[1];
            } else {
              // Replace the previous value
              iThData[1] = value;
            }
          } else {
            // Splice into the correct position to keep timestamps in order
            this.data.splice(i + 1, 0, [timestamp, value]);
          }

          break;
        }

        i--;
        if (i < 0) {
          // This new item is the oldest data
          this.data.splice(0, 0, [timestamp, value]);

          break;
        }
      }
    } else {
      // It's the first element
      this.data.push([timestamp, value]);
    }

    this.maxValue = isNaN(this.maxValue) ? value : Math.max(this.maxValue, value);
    this.minValue = isNaN(this.minValue) ? value : Math.min(this.minValue, value);
  };

  TimeSeries.prototype.dropOldData = function(oldestValidTime, maxDataSetLength) {
    // We must always keep one expired data point as we need this to draw the
    // line that comes into the chart from the left, but any points prior to that can be removed.
    var removeCount = 0;
    while (this.data.length - removeCount >= maxDataSetLength && this.data[removeCount + 1][0] < oldestValidTime) {
      removeCount++;
    }
    if (removeCount !== 0) {
      this.data.splice(0, removeCount);
    }
  };

  /**
   * Initialises a new <code>SmoothieChart</code>.
   *
   * Options are optional, and should be of the form below. Just specify the values you
   * need and the rest will be given sensible defaults as shown:
   *
   * <pre>
   * {
   *   minValue: undefined,                      // specify to clamp the lower y-axis to a given value
   *   maxValue: undefined,                      // specify to clamp the upper y-axis to a given value
   *   maxValueScale: 1,                         // allows proportional padding to be added above the chart. for 10% padding, specify 1.1.
   *   minValueScale: 1,                         // allows proportional padding to be added below the chart. for 10% padding, specify 1.1.
   *   yRangeFunction: undefined,                // function({min: , max: }) { return {min: , max: }; }
   *   scaleSmoothing: 0.125,                    // controls the rate at which y-value zoom animation occurs
   *   millisPerPixel: 20,                       // sets the speed at which the chart pans by
   *   enableDpiScaling: true,                   // support rendering at different DPI depending on the device
   *   yMinFormatter: function(min, precision) { // callback function that formats the min y value label
   *     return parseFloat(min).toFixed(precision);
   *   },
   *   yMaxFormatter: function(max, precision) { // callback function that formats the max y value label
   *     return parseFloat(max).toFixed(precision);
   *   },
   *   yIntermediateFormatter: function(intermediate, precision) { // callback function that formats the intermediate y value labels
   *     return parseFloat(intermediate).toFixed(precision);
   *   },
   *   maxDataSetLength: 2,
   *   interpolation: 'bezier'                   // one of 'bezier', 'linear', or 'step'
   *   timestampFormatter: null,                 // optional function to format time stamps for bottom of chart
   *                                             // you may use SmoothieChart.timeFormatter, or your own: function(date) { return ''; }
   *   scrollBackwards: false,                   // reverse the scroll direction of the chart
   *   horizontalLines: [],                      // [ { value: 0, color: '#ffffff', lineWidth: 1 } ]
   *   grid:
   *   {
   *     fillStyle: '#000000',                   // the background colour of the chart
   *     lineWidth: 1,                           // the pixel width of grid lines
   *     strokeStyle: '#777777',                 // colour of grid lines
   *     millisPerLine: 1000,                    // distance between vertical grid lines
   *     verticalSections: 2,                    // number of vertical sections marked out by horizontal grid lines
   *     borderVisible: true                     // whether the grid lines trace the border of the chart or not
   *   },
   *   labels
   *   {
   *     disabled: false,                        // enables/disables labels showing the min/max values
   *     fillStyle: '#ffffff',                   // colour for text of labels,
   *     fontSize: 15,
   *     fontFamily: 'sans-serif',
   *     precision: 2,
   *     showIntermediateLabels: false,          // shows intermediate labels between min and max values along y axis
   *     intermediateLabelSameAxis: true,
   *   },
   *   title
   *   {
   *     text: '',                               // the text to display on the left side of the chart
   *     fillStyle: '#ffffff',                   // colour for text
   *     fontSize: 15,
   *     fontFamily: 'sans-serif',
   *     verticalAlign: 'middle'                 // one of 'top', 'middle', or 'bottom'
   *   },
   *   tooltip: false                            // show tooltip when mouse is over the chart
   *   tooltipLine: {                            // properties for a vertical line at the cursor position
   *     lineWidth: 1,
   *     strokeStyle: '#BBBBBB'
   *   },
   *   tooltipFormatter: SmoothieChart.tooltipFormatter, // formatter function for tooltip text
   *   nonRealtimeData: false,                   // use time of latest data as current time
   *   displayDataFromPercentile: 1,             // display not latest data, but data from the given percentile
   *                                             // useful when trying to see old data saved by setting a high value for maxDataSetLength
   *                                             // should be a value between 0 and 1
   *   responsive: false,                        // whether the chart should adapt to the size of the canvas
   *   limitFPS: 0                               // maximum frame rate the chart will render at, in FPS (zero means no limit)
   * }
   * </pre>
   *
   * @constructor
   */
  function SmoothieChart(options) {
    this.options = Util.extend({}, SmoothieChart.defaultChartOptions, options);
    this.seriesSet = [];
    this.currentValueRange = 1;
    this.currentVisMinValue = 0;
    this.lastRenderTimeMillis = 0;
    this.lastChartTimestamp = 0;

    this.mousemove = this.mousemove.bind(this);
    this.mouseout = this.mouseout.bind(this);
  }

  /** Formats the HTML string content of the tooltip. */
  SmoothieChart.tooltipFormatter = function (timestamp, data) {
      var timestampFormatter = this.options.timestampFormatter || SmoothieChart.timeFormatter,
          // A dummy element to hold children. Maybe there's a better way.
          elements = document.createElement('div'),
          label;
      elements.appendChild(document.createTextNode(
        timestampFormatter(new Date(timestamp))
      ));

      for (var i = 0; i < data.length; ++i) {
        label = data[i].series.options.tooltipLabel || ''
        if (label !== ''){
            label = label + ' ';
        }
        var dataEl = document.createElement('span');
        dataEl.style.color = data[i].series.options.strokeStyle;
        dataEl.appendChild(document.createTextNode(
          label + this.options.yMaxFormatter(data[i].value, this.options.labels.precision)
        ));
        elements.appendChild(document.createElement('br'));
        elements.appendChild(dataEl);
      }

      return elements.innerHTML;
  };

  SmoothieChart.defaultChartOptions = {
    millisPerPixel: 20,
    enableDpiScaling: true,
    yMinFormatter: function(min, precision) {
      return parseFloat(min).toFixed(precision);
    },
    yMaxFormatter: function(max, precision) {
      return parseFloat(max).toFixed(precision);
    },
    yIntermediateFormatter: function(intermediate, precision) {
      return parseFloat(intermediate).toFixed(precision);
    },
    maxValueScale: 1,
    minValueScale: 1,
    interpolation: 'bezier',
    scaleSmoothing: 0.125,
    maxDataSetLength: 2,
    scrollBackwards: false,
    displayDataFromPercentile: 1,
    grid: {
      fillStyle: '#000000',
      strokeStyle: '#777777',
      lineWidth: 2,
      millisPerLine: 1000,
      verticalSections: 2,
      borderVisible: true
    },
    labels: {
      fillStyle: '#ffffff',
      disabled: false,
      fontSize: 10,
      fontFamily: 'monospace',
      precision: 2,
      showIntermediateLabels: false,
      intermediateLabelSameAxis: true,
    },
    title: {
      text: '',
      fillStyle: '#ffffff',
      fontSize: 15,
      fontFamily: 'monospace',
      verticalAlign: 'middle'
    },
    horizontalLines: [],
    tooltip: false,
    tooltipLine: {
      lineWidth: 1,
      strokeStyle: '#BBBBBB'
    },
    tooltipFormatter: SmoothieChart.tooltipFormatter,
    nonRealtimeData: false,
    responsive: false,
    limitFPS: 0
  };

  // Based on http://inspirit.github.com/jsfeat/js/compatibility.js
  SmoothieChart.AnimateCompatibility = (function() {
    var requestAnimationFrame = function(callback, element) {
          var requestAnimationFrame =
            window.requestAnimationFrame        ||
            window.webkitRequestAnimationFrame  ||
            window.mozRequestAnimationFrame     ||
            window.oRequestAnimationFrame       ||
            window.msRequestAnimationFrame      ||
            function(callback) {
              return window.setTimeout(function() {
                callback(Date.now());
              }, 16);
            };
          return requestAnimationFrame.call(window, callback, element);
        },
        cancelAnimationFrame = function(id) {
          var cancelAnimationFrame =
            window.cancelAnimationFrame ||
            function(id) {
              clearTimeout(id);
            };
          return cancelAnimationFrame.call(window, id);
        };

    return {
      requestAnimationFrame: requestAnimationFrame,
      cancelAnimationFrame: cancelAnimationFrame
    };
  })();

  SmoothieChart.defaultSeriesPresentationOptions = {
    lineWidth: 1,
    strokeStyle: '#ffffff',
    // Maybe default to false in the next breaking version.
    fillToBottom: true,
  };

  /**
   * Adds a <code>TimeSeries</code> to this chart, with optional presentation options.
   *
   * Presentation options should be of the form (defaults shown):
   *
   * <pre>
   * {
   *   lineWidth: 1,
   *   strokeStyle: '#ffffff',
   *   fillStyle: undefined,
   *   interpolation: undefined;
   *   tooltipLabel: undefined,
   *   fillToBottom: true,
   * }
   * </pre>
   */
  SmoothieChart.prototype.addTimeSeries = function(timeSeries, options) {
    this.seriesSet.push({timeSeries: timeSeries, options: Util.extend({}, SmoothieChart.defaultSeriesPresentationOptions, options)});
    if (timeSeries.options.resetBounds && timeSeries.options.resetBoundsInterval > 0) {
      timeSeries.resetBoundsTimerId = setInterval(
        function() {
          timeSeries.resetBounds();
        },
        timeSeries.options.resetBoundsInterval
      );
    }
  };

  /**
   * Removes the specified <code>TimeSeries</code> from the chart.
   */
  SmoothieChart.prototype.removeTimeSeries = function(timeSeries) {
    // Find the correct timeseries to remove, and remove it
    var numSeries = this.seriesSet.length;
    for (var i = 0; i < numSeries; i++) {
      if (this.seriesSet[i].timeSeries === timeSeries) {
        this.seriesSet.splice(i, 1);
        break;
      }
    }
    // If a timer was operating for that timeseries, remove it
    if (timeSeries.resetBoundsTimerId) {
      // Stop resetting the bounds, if we were
      clearInterval(timeSeries.resetBoundsTimerId);
    }
  };

  /**
   * Gets render options for the specified <code>TimeSeries</code>.
   *
   * As you may use a single <code>TimeSeries</code> in multiple charts with different formatting in each usage,
   * these settings are stored in the chart.
   */
  SmoothieChart.prototype.getTimeSeriesOptions = function(timeSeries) {
    // Find the correct timeseries to remove, and remove it
    var numSeries = this.seriesSet.length;
    for (var i = 0; i < numSeries; i++) {
      if (this.seriesSet[i].timeSeries === timeSeries) {
        return this.seriesSet[i].options;
      }
    }
  };

  /**
   * Brings the specified <code>TimeSeries</code> to the top of the chart. It will be rendered last.
   */
  SmoothieChart.prototype.bringToFront = function(timeSeries) {
    // Find the correct timeseries to remove, and remove it
    var numSeries = this.seriesSet.length;
    for (var i = 0; i < numSeries; i++) {
      if (this.seriesSet[i].timeSeries === timeSeries) {
        var set = this.seriesSet.splice(i, 1);
        this.seriesSet.push(set[0]);
        break;
      }
    }
  };

  /**
   * Instructs the <code>SmoothieChart</code> to start rendering to the provided canvas, with specified delay.
   *
   * @param canvas the target canvas element
   * @param delayMillis an amount of time to wait before a data point is shown. This can prevent the end of the series
   * from appearing on screen, with new values flashing into view, at the expense of some latency.
   */
  SmoothieChart.prototype.streamTo = function(canvas, delayMillis) {
    this.canvas = canvas;

    this.clientWidth = parseInt(this.canvas.getAttribute('width'));
    this.clientHeight = parseInt(this.canvas.getAttribute('height'));

    this.delay = delayMillis;
    this.start();
  };

  SmoothieChart.prototype.getTooltipEl = function () {
    // Create the tool tip element lazily
    if (!this.tooltipEl) {
      this.tooltipEl = document.createElement('div');
      this.tooltipEl.className = 'smoothie-chart-tooltip';
      this.tooltipEl.style.pointerEvents = 'none';
      this.tooltipEl.style.position = 'absolute';
      this.tooltipEl.style.display = 'none';
      document.body.appendChild(this.tooltipEl);
    }
    return this.tooltipEl;
  };

  SmoothieChart.prototype.updateTooltip = function () {
    if(!this.options.tooltip){
     return; 
    }
    var el = this.getTooltipEl();

    if (!this.mouseover || !this.options.tooltip) {
      el.style.display = 'none';
      return;
    }

    var time = this.lastChartTimestamp;

    // x pixel to time
    var t = this.options.scrollBackwards
      ? time - this.mouseX * this.options.millisPerPixel
      : time - (this.clientWidth - this.mouseX) * this.options.millisPerPixel;

    var data = [];

     // For each data set...
    for (var d = 0; d < this.seriesSet.length; d++) {
      var timeSeries = this.seriesSet[d].timeSeries;
      if (timeSeries.disabled) {
          continue;
      }

      // find datapoint closest to time 't'
      var closeIdx = Util.binarySearch(timeSeries.data, t);
      if (closeIdx > 0 && closeIdx < timeSeries.data.length) {
        data.push({ series: this.seriesSet[d], index: closeIdx, value: timeSeries.data[closeIdx][1] });
      }
    }

    if (data.length) {
      // TODO make `tooltipFormatter` return element(s) instead of an HTML string so it's harder for users
      // to introduce an XSS. This would be a breaking change.
      el.innerHTML = this.options.tooltipFormatter.call(this, t, data);
      el.style.display = 'block';
    } else {
      el.style.display = 'none';
    }
  };

  SmoothieChart.prototype.mousemove = function (evt) {
    this.mouseover = true;
    this.mouseX = evt.offsetX;
    this.mouseY = evt.offsetY;
    this.mousePageX = evt.pageX;
    this.mousePageY = evt.pageY;
    if(!this.options.tooltip){
     return; 
    }
    var el = this.getTooltipEl();
    el.style.top = Math.round(this.mousePageY) + 'px';
    el.style.left = Math.round(this.mousePageX) + 'px';
    this.updateTooltip();
  };

  SmoothieChart.prototype.mouseout = function () {
    this.mouseover = false;
    this.mouseX = this.mouseY = -1;
    if (this.tooltipEl)
      this.tooltipEl.style.display = 'none';
  };

  /**
   * Make sure the canvas has the optimal resolution for the device's pixel ratio.
   */
  SmoothieChart.prototype.resize = function () {
    var dpr = !this.options.enableDpiScaling || !window ? 1 : window.devicePixelRatio,
        width, height;
    if (this.options.responsive) {
      // Newer behaviour: Use the canvas's size in the layout, and set the internal
      // resolution according to that size and the device pixel ratio (eg: high DPI)
      width = this.canvas.offsetWidth;
      height = this.canvas.offsetHeight;

      if (width !== this.lastWidth) {
        this.lastWidth = width;
        this.canvas.setAttribute('width', (Math.floor(width * dpr)).toString());
        this.canvas.getContext('2d').scale(dpr, dpr);
      }
      if (height !== this.lastHeight) {
        this.lastHeight = height;
        this.canvas.setAttribute('height', (Math.floor(height * dpr)).toString());
        this.canvas.getContext('2d').scale(dpr, dpr);
      }

      this.clientWidth = width;
      this.clientHeight = height;
    } else {
      width = parseInt(this.canvas.getAttribute('width'));
      height = parseInt(this.canvas.getAttribute('height'));

      if (dpr !== 1) {
        // Older behaviour: use the canvas's inner dimensions and scale the element's size
        // according to that size and the device pixel ratio (eg: high DPI)

        if (Math.floor(this.clientWidth * dpr) !== width) {
          this.canvas.setAttribute('width', (Math.floor(width * dpr)).toString());
          this.canvas.style.width = width + 'px';
          this.clientWidth = width;
          this.canvas.getContext('2d').scale(dpr, dpr);
        }

        if (Math.floor(this.clientHeight * dpr) !== height) {
          this.canvas.setAttribute('height', (Math.floor(height * dpr)).toString());
          this.canvas.style.height = height + 'px';
          this.clientHeight = height;
          this.canvas.getContext('2d').scale(dpr, dpr);
        }
      } else {
        this.clientWidth = width;
        this.clientHeight = height;
      }
    }
  };

  /**
   * Starts the animation of this chart.
   */
  SmoothieChart.prototype.start = function() {
    if (this.frame) {
      // We're already running, so just return
      return;
    }

    this.canvas.addEventListener('mousemove', this.mousemove);
    this.canvas.addEventListener('mouseout', this.mouseout);

    // Renders a frame, and queues the next frame for later rendering
    var animate = function() {
      this.frame = SmoothieChart.AnimateCompatibility.requestAnimationFrame(function() {
        if(this.options.nonRealtimeData){
           var dateZero = new Date(0);
           // find the data point with the latest timestamp
           var maxTimeStamp = this.seriesSet.reduce(function(max, series){
             var dataSet = series.timeSeries.data;
             var indexToCheck = Math.round(this.options.displayDataFromPercentile * dataSet.length) - 1;
             indexToCheck = indexToCheck >= 0 ? indexToCheck : 0;
             indexToCheck = indexToCheck <= dataSet.length -1 ? indexToCheck : dataSet.length -1;
             if(dataSet && dataSet.length > 0)
             {
              // timestamp corresponds to element 0 of the data point
              var lastDataTimeStamp = dataSet[indexToCheck][0];
              max = max > lastDataTimeStamp ? max : lastDataTimeStamp;
             }
             return max;
          }.bind(this), dateZero);
          // use the max timestamp as current time
          this.render(this.canvas, maxTimeStamp > dateZero ? maxTimeStamp : null);
        } else {
          this.render();
        }
        animate();
      }.bind(this));
    }.bind(this);

    animate();
  };

  /**
   * Stops the animation of this chart.
   */
  SmoothieChart.prototype.stop = function() {
    if (this.frame) {
      SmoothieChart.AnimateCompatibility.cancelAnimationFrame(this.frame);
      delete this.frame;
      this.canvas.removeEventListener('mousemove', this.mousemove);
      this.canvas.removeEventListener('mouseout', this.mouseout);
    }
  };

  SmoothieChart.prototype.updateValueRange = function() {
    // Calculate the current scale of the chart, from all time series.
    var chartOptions = this.options,
        chartMaxValue = Number.NaN,
        chartMinValue = Number.NaN;

    for (var d = 0; d < this.seriesSet.length; d++) {
      // TODO(ndunn): We could calculate / track these values as they stream in.
      var timeSeries = this.seriesSet[d].timeSeries;
      if (timeSeries.disabled) {
          continue;
      }

      if (!isNaN(timeSeries.maxValue)) {
        chartMaxValue = !isNaN(chartMaxValue) ? Math.max(chartMaxValue, timeSeries.maxValue) : timeSeries.maxValue;
      }

      if (!isNaN(timeSeries.minValue)) {
        chartMinValue = !isNaN(chartMinValue) ? Math.min(chartMinValue, timeSeries.minValue) : timeSeries.minValue;
      }
    }

    // Scale the chartMaxValue to add padding at the top if required
    if (chartOptions.maxValue != null) {
      chartMaxValue = chartOptions.maxValue;
    } else {
      chartMaxValue *= chartOptions.maxValueScale;
    }

    // Set the minimum if we've specified one
    if (chartOptions.minValue != null) {
      chartMinValue = chartOptions.minValue;
    } else {
      chartMinValue -= Math.abs(chartMinValue * chartOptions.minValueScale - chartMinValue);
    }

    // If a custom range function is set, call it
    if (this.options.yRangeFunction) {
      var range = this.options.yRangeFunction({min: chartMinValue, max: chartMaxValue});
      chartMinValue = range.min;
      chartMaxValue = range.max;
    }

    if (!isNaN(chartMaxValue) && !isNaN(chartMinValue)) {
      var targetValueRange = chartMaxValue - chartMinValue;
      var valueRangeDiff = (targetValueRange - this.currentValueRange);
      var minValueDiff = (chartMinValue - this.currentVisMinValue);
      this.isAnimatingScale = Math.abs(valueRangeDiff) > 0.1 || Math.abs(minValueDiff) > 0.1;
      this.currentValueRange += chartOptions.scaleSmoothing * valueRangeDiff;
      this.currentVisMinValue += chartOptions.scaleSmoothing * minValueDiff;
    }

    this.valueRange = { min: chartMinValue, max: chartMaxValue };
  };

  SmoothieChart.prototype.render = function(canvas, time) {
    var chartOptions = this.options,
        nowMillis = Date.now();

    // Respect any frame rate limit.
    if (chartOptions.limitFPS > 0 && nowMillis - this.lastRenderTimeMillis < (1000/chartOptions.limitFPS))
      return;

    time = (time || nowMillis) - (this.delay || 0);

    // Round time down to pixel granularity, so that pixel sample values remain the same,
    // just shifted 1px to the left, so motion appears smoother.
    time -= time % chartOptions.millisPerPixel;

    if (!this.isAnimatingScale) {
      // We're not animating. We can use the last render time and the scroll speed to work out whether
      // we actually need to paint anything yet. If not, we can return immediately.
      var sameTime = this.lastChartTimestamp === time;
      if (sameTime) {
        // Render at least every 1/6th of a second. The canvas may be resized, which there is
        // no reliable way to detect.
        var needToRenderInCaseCanvasResized = nowMillis - this.lastRenderTimeMillis > 1000/6;
        if (!needToRenderInCaseCanvasResized) {
          return;
        }
      }
    }

    this.lastRenderTimeMillis = nowMillis;
    this.lastChartTimestamp = time;

    this.resize();

    canvas = canvas || this.canvas;
    var context = canvas.getContext('2d'),
        // Using `this.clientWidth` instead of `canvas.clientWidth` because the latter is slow.
        dimensions = { top: 0, left: 0, width: this.clientWidth, height: this.clientHeight },
        // Calculate the threshold time for the oldest data points.
        oldestValidTime = time - (dimensions.width * chartOptions.millisPerPixel),
        valueToYPosition = function(value, lineWidth) {
          var offset = value - this.currentVisMinValue,
              unsnapped = this.currentValueRange === 0
                ? dimensions.height
                : dimensions.height * (1 - offset / this.currentValueRange);
          return Util.pixelSnap(unsnapped, lineWidth);
        }.bind(this),
        timeToXPosition = function(t, lineWidth) {
          // Why not write it as `(time - t) / chartOptions.millisPerPixel`:
          // If a datapoint's `t` is very close or is at the center of a pixel, that expression,
          // due to floating point error, may take value whose `% 1` sometimes is very close to
          // 0 and sometimes is close to 1, depending on the value of render time (`time`),
          // which would make `pixelSnap` snap it sometimes to the right and sometimes to the left,
          // which would look like it's jumping.
          // You can try the default examples, with `millisPerPixel = 100 / 3` and
          // `grid.lineWidth = 1`. The grid would jump.
          // Writing it this way seems to avoid such inconsistency because in the above example
          // `offset` is (almost?) always a whole number.
          // TODO Maybe there's a more elegant (and reliable?) way.
          var offset = time / chartOptions.millisPerPixel - t / chartOptions.millisPerPixel;
          var unsnapped = chartOptions.scrollBackwards
            ? offset
            : dimensions.width - offset;
          return Util.pixelSnap(unsnapped, lineWidth);
        };

    this.updateValueRange();

    context.font = chartOptions.labels.fontSize + 'px ' + chartOptions.labels.fontFamily;

    // Save the state of the canvas context, any transformations applied in this method
    // will get removed from the stack at the end of this method when .restore() is called.
    context.save();

    // Move the origin.
    context.translate(dimensions.left, dimensions.top);

    // Create a clipped rectangle - anything we draw will be constrained to this rectangle.
    // This prevents the occasional pixels from curves near the edges overrunning and creating
    // screen cheese (that phrase should need no explanation).
    context.beginPath();
    context.rect(0, 0, dimensions.width, dimensions.height);
    context.clip();

    // Clear the working area.
    context.save();
    context.fillStyle = chartOptions.grid.fillStyle;
    context.clearRect(0, 0, dimensions.width, dimensions.height);
    context.fillRect(0, 0, dimensions.width, dimensions.height);
    context.restore();

    // Grid lines...
    context.save();
    context.lineWidth = chartOptions.grid.lineWidth;
    context.strokeStyle = chartOptions.grid.strokeStyle;
    // Vertical (time) dividers.
    if (chartOptions.grid.millisPerLine > 0) {
      context.beginPath();
      for (var t = time - (time % chartOptions.grid.millisPerLine);
           t >= oldestValidTime;
           t -= chartOptions.grid.millisPerLine) {
        var gx = timeToXPosition(t, chartOptions.grid.lineWidth);
        context.moveTo(gx, 0);
        context.lineTo(gx, dimensions.height);
      }
      context.stroke();
    }

    // Horizontal (value) dividers.
    for (var v = 1; v < chartOptions.grid.verticalSections; v++) {
      var gy = Util.pixelSnap(v * dimensions.height / chartOptions.grid.verticalSections, chartOptions.grid.lineWidth);
      context.beginPath();
      context.moveTo(0, gy);
      context.lineTo(dimensions.width, gy);
      context.stroke();
    }
    // Bounding rectangle.
    if (chartOptions.grid.borderVisible) {
      context.strokeRect(0, 0, dimensions.width, dimensions.height);
    }
    context.restore();

    // Draw any horizontal lines...
    if (chartOptions.horizontalLines && chartOptions.horizontalLines.length) {
      for (var hl = 0; hl < chartOptions.horizontalLines.length; hl++) {
        var line = chartOptions.horizontalLines[hl],
            lineWidth = line.lineWidth || 1,
            hly = valueToYPosition(line.value, lineWidth);
        context.strokeStyle = line.color || '#ffffff';
        context.lineWidth = lineWidth;
        context.beginPath();
        context.moveTo(0, hly);
        context.lineTo(dimensions.width, hly);
        context.stroke();
      }
    }

    // For each data set...
    for (var d = 0; d < this.seriesSet.length; d++) {
      var timeSeries = this.seriesSet[d].timeSeries,
          dataSet = timeSeries.data;

      // Delete old data that's moved off the left of the chart.
      timeSeries.dropOldData(oldestValidTime, chartOptions.maxDataSetLength);
      if (dataSet.length <= 1 || timeSeries.disabled) {
          continue;
      }
      context.save();

      var seriesOptions = this.seriesSet[d].options,
          // Keep in mind that `context.lineWidth = 0` doesn't actually set it to `0`.
          drawStroke = seriesOptions.strokeStyle && seriesOptions.strokeStyle !== 'none',
          lineWidthMaybeZero = drawStroke ? seriesOptions.lineWidth : 0;

      // Draw the line...
      context.beginPath();
      // Retain lastX, lastY for calculating the control points of bezier curves.
      var firstX = timeToXPosition(dataSet[0][0], lineWidthMaybeZero),
        firstY = valueToYPosition(dataSet[0][1], lineWidthMaybeZero),
        lastX = firstX,
        lastY = firstY,
        draw;
      context.moveTo(firstX, firstY);
      switch (seriesOptions.interpolation || chartOptions.interpolation) {
        case "linear":
        case "line": {
          draw = function(x, y, lastX, lastY) {
            context.lineTo(x,y);
          }
          break;
        }
        case "bezier":
        default: {
          // Great explanation of Bezier curves: http://en.wikipedia.org/wiki/Bezier_curve#Quadratic_curves
          //
          // Assuming A was the last point in the line plotted and B is the new point,
          // we draw a curve with control points P and Q as below.
          //
          // A---P
          //     |
          //     |
          //     |
          //     Q---B
          //
          // Importantly, A and P are at the same y coordinate, as are B and Q. This is
          // so adjacent curves appear to flow as one.
          //
          draw = function(x, y, lastX, lastY) {
            context.bezierCurveTo( // startPoint (A) is implicit from last iteration of loop
              Math.round((lastX + x) / 2), lastY, // controlPoint1 (P)
              Math.round((lastX + x)) / 2, y, // controlPoint2 (Q)
              x, y); // endPoint (B)
          }
          break;
        }
        case "step": {
          draw = function(x, y, lastX, lastY) {
            context.lineTo(x,lastY);
            context.lineTo(x,y);
          }
          break;
        }
      }

      for (var i = 1; i < dataSet.length; i++) {
        var iThData = dataSet[i],
            x = timeToXPosition(iThData[0], lineWidthMaybeZero),
            y = valueToYPosition(iThData[1], lineWidthMaybeZero);
        draw(x, y, lastX, lastY);
        lastX = x; lastY = y;
      }

      if (drawStroke) {
        context.lineWidth = seriesOptions.lineWidth;
        context.strokeStyle = seriesOptions.strokeStyle;
        context.stroke();
      }

      if (seriesOptions.fillStyle) {
        // Close up the fill region.
        var fillEndY = seriesOptions.fillToBottom
          ? dimensions.height + lineWidthMaybeZero + 1
          : valueToYPosition(0, 0);
        context.lineTo(lastX, fillEndY);
        context.lineTo(firstX, fillEndY);

        context.fillStyle = seriesOptions.fillStyle;
        context.fill();
      }

      context.restore();
    }

    if (chartOptions.tooltip && this.mouseX >= 0) {
      // Draw vertical bar to show tooltip position
      context.lineWidth = chartOptions.tooltipLine.lineWidth;
      context.strokeStyle = chartOptions.tooltipLine.strokeStyle;
      context.beginPath();
      context.moveTo(this.mouseX, 0);
      context.lineTo(this.mouseX, dimensions.height);
      context.stroke();
    }
    this.updateTooltip();

    var labelsOptions = chartOptions.labels;
    // Draw the axis values on the chart.
    if (!labelsOptions.disabled && !isNaN(this.valueRange.min) && !isNaN(this.valueRange.max)) {
      var maxValueString = chartOptions.yMaxFormatter(this.valueRange.max, labelsOptions.precision),
          minValueString = chartOptions.yMinFormatter(this.valueRange.min, labelsOptions.precision),
          maxLabelPos = chartOptions.scrollBackwards ? 0 : dimensions.width - context.measureText(maxValueString).width - 2,
          minLabelPos = chartOptions.scrollBackwards ? 0 : dimensions.width - context.measureText(minValueString).width - 2;
      context.fillStyle = labelsOptions.fillStyle;
      context.fillText(maxValueString, maxLabelPos, labelsOptions.fontSize);
      context.fillText(minValueString, minLabelPos, dimensions.height - 2);
    }

    // Display intermediate y axis labels along y-axis to the left of the chart
    if ( labelsOptions.showIntermediateLabels
          && !isNaN(this.valueRange.min) && !isNaN(this.valueRange.max)
          && chartOptions.grid.verticalSections > 0) {
      // show a label above every vertical section divider
      var step = (this.valueRange.max - this.valueRange.min) / chartOptions.grid.verticalSections;
      var stepPixels = dimensions.height / chartOptions.grid.verticalSections;
      for (var v = 1; v < chartOptions.grid.verticalSections; v++) {
        var gy = dimensions.height - Math.round(v * stepPixels),
            yValue = chartOptions.yIntermediateFormatter(this.valueRange.min + (v * step), labelsOptions.precision),
            //left of right axis?
            intermediateLabelPos =
              labelsOptions.intermediateLabelSameAxis
              ? (chartOptions.scrollBackwards ? 0 : dimensions.width - context.measureText(yValue).width - 2)
              : (chartOptions.scrollBackwards ? dimensions.width - context.measureText(yValue).width - 2 : 0);

        context.fillText(yValue, intermediateLabelPos, gy - chartOptions.grid.lineWidth);
      }
    }

    // Display timestamps along x-axis at the bottom of the chart.
    if (chartOptions.timestampFormatter && chartOptions.grid.millisPerLine > 0) {
      var textUntilX = chartOptions.scrollBackwards
        ? context.measureText(minValueString).width
        : dimensions.width - context.measureText(minValueString).width + 4;
      for (var t = time - (time % chartOptions.grid.millisPerLine);
           t >= oldestValidTime;
           t -= chartOptions.grid.millisPerLine) {
        var gx = timeToXPosition(t, 0);
        // Only draw the timestamp if it won't overlap with the previously drawn one.
        if ((!chartOptions.scrollBackwards && gx < textUntilX) || (chartOptions.scrollBackwards && gx > textUntilX))  {
          // Formats the timestamp based on user specified formatting function
          // SmoothieChart.timeFormatter function above is one such formatting option
          var tx = new Date(t),
            ts = chartOptions.timestampFormatter(tx),
            tsWidth = context.measureText(ts).width;

          textUntilX = chartOptions.scrollBackwards
            ? gx + tsWidth + 2
            : gx - tsWidth - 2;

          context.fillStyle = chartOptions.labels.fillStyle;
          if(chartOptions.scrollBackwards) {
            context.fillText(ts, gx, dimensions.height - 2);
          } else {
            context.fillText(ts, gx - tsWidth, dimensions.height - 2);
          }
        }
      }
    }

    // Display title.
    if (chartOptions.title.text !== '') {
      context.font = chartOptions.title.fontSize + 'px ' + chartOptions.title.fontFamily;
      var titleXPos = chartOptions.scrollBackwards ? dimensions.width - context.measureText(chartOptions.title.text).width - 2 : 2;
      if (chartOptions.title.verticalAlign == 'bottom') {
        context.textBaseline = 'bottom';
        var titleYPos = dimensions.height;
      } else if (chartOptions.title.verticalAlign == 'middle') {
        context.textBaseline = 'middle';
        var titleYPos = dimensions.height / 2;
      } else {
        context.textBaseline = 'top';
        var titleYPos = 0;
      }
      context.fillStyle = chartOptions.title.fillStyle;
      context.fillText(chartOptions.title.text, titleXPos, titleYPos);
    }

    context.restore(); // See .save() above.
  };

  // Sample timestamp formatting function
  SmoothieChart.timeFormatter = function(date) {
    function pad2(number) { return (number < 10 ? '0' : '') + number }
    return pad2(date.getHours()) + ':' + pad2(date.getMinutes()) + ':' + pad2(date.getSeconds());
  };

  exports.TimeSeries = TimeSeries;
  exports.SmoothieChart = SmoothieChart;

})(typeof exports === 'undefined' ? this : exports);

