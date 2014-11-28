/**
 * 
 */
(function($) {
	$.dataTableCustom = function(targetSelector, options) {
		options = $.extend({
			//"sDom": "<'row-fluid table_top_bar'<'span12'<'to_hide_phone' f>>>t<'row-fluid control-group full top' <'span4 to_hide_tablet'l><'span8 pagination'p>>",	
			"bJQueryUI": false,
			"bPaginate": true,
			"sPaginationType": "full_numbers",
	        "bProcessing": true,
	        "bServerSide": true	,
	        "aaSorting": []
		}, options);
		
		$(targetSelector).dataTable(options);
	};
	
	$.extend( $.fn.dataTableExt.oStdClasses, {
		"s`": "dataTables_wrapper form-inline"
	});

	
	
	
})(jQuery);