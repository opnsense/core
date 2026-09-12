(function($) {
	$.fn.qrcode = function(options) {
		if (typeof options === 'string') {
			options	= {
				data: options
			};
		}
		options	= $.extend({
			cellSize: 3,
			margin: undefined, // automatic
			errorCorrectionLevel: 'M', // 'L','M','Q','H'
			data: ''
		}, options);

		var qr = qrcode(0, options.errorCorrectionLevel); // 0 for automatic typeNumber
		qr.addData(options.data);
		qr.make();
		var img = qr.createImgTag(options.cellSize, options.margin);

		return this.append(img);
	};
})(jQuery);
