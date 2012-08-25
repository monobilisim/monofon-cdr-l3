$(document).ready(function() {

	$('.pagination a.disabled').click(function(e) {
		e.preventDefault();
	});
	
	$(function() {
		$(".datepicker").datepicker({
			dateFormat: "dd.mm.yy",
			changeMonth: true,
			changeYear: true,
			monthNamesShort: ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran',
			'Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'],
			yearRange: "c-3:c+0"
		});
	});
	
	var per_page = $("#per-page").val();
	
	$("#per-page").change(function() {
		var url = window.location.href;
		var regex = new RegExp("(per_page=)([0-9]+)");
		var match = regex.exec(url);
		if (match === null)
		{
			if (url.indexOf("?") === -1)
			{
				url = url + "?per_page=" + $(this).val();
			}
			else
			{
				url = url + "&per_page=" + $(this).val();
			}
		}
		else
		{
			url = url.replace(match[0], match[1] + $(this).val());
		}
		
		var page = $("#page").val();
		
		if (parseInt(page) !== 1) {
			var total = $("#total").val();
			var total_pages = (total / per_page);
			if (parseInt(total_pages, 10) !== total_pages) total_pages = parseInt(total_pages, 10) + 1;
			var new_per_page = $(this).val();
			var new_total_pages = (total / new_per_page);
			if (parseInt(new_total_pages, 10) !== new_total_pages) new_total_pages = parseInt(new_total_pages, 10) + 1;
			var new_page = ((new_total_pages * page) / total_pages);
			new_page = parseInt(new_page, 10);
			
			var regex = new RegExp("([?,&])(page=)([0-9]+)");
			if (parseInt(new_page) === 1)
			{
				var match = regex.exec(url);
				url = url.replace(regex, "");
				if (match[1] === "?")
				{
					url = url.replace("&", "?");
				}
			}
			else
			{
				url = url.replace(regex, "$1$2" + new_page);
			}
		}
		
		window.location = url;
	});
	
	$(".btn-listen").click(function() {
		$("#listen").attr("uniqueid", $(this).attr("uniqueid"));
	});
	$("#listen").on('shown', function() {
		$(".modal-body > p").load("/cdr/listen/" + $(this).attr("uniqueid"));
	});
	$("#listen").on('hidden', function() {
		var spinner = '<span class="spinner"></span>';
		$(".modal-body > p").html(spinner);
	});

});
