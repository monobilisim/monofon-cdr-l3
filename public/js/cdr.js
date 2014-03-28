$(document).ready(function() {

	$('.pagination a.disabled').click(function(e) {
		e.preventDefault();
	});

	$(".datetimepicker").datetimepicker({
		dateFormat: "dd.mm.yy -",
		changeMonth: true,
		changeYear: true,
		monthNamesShort: ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran',
		'Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'],
		yearRange: "c-3:c+0",
		timeText: '',
	});

	$("input[name='datestart']").change(function() {
                $("input[name='dateend']").val($(this).val());
        });

	$('.icon-question-sign').bind('mouseenter mouseleave', function() {
		$(this).next().toggle();
	});

	var input_src = $('#input_src');
	var input_dst = $('#input_dst');
	var input_src_dst = $('#input_src_dst');

	if (input_src.val() || input_dst.val()) {
		input_src_dst.addClass('grayed-out');
	}
	if (input_src_dst.val()) {
		$([input_src[0], input_dst[0]]).addClass('grayed-out');
	}

	input_src_dst.keypress(function() {
		if (!$(this).val()) {
			$([input_src[0], input_dst[0]]).val("");
		}
	});
	input_src_dst.focus(function() {
		$(this).removeClass('grayed-out');
		$([input_src[0], input_dst[0]]).addClass('grayed-out');
	});
	$([input_src[0], input_dst[0]]).keypress(function() {
		if (!$(this).val()) {
			input_src_dst.val("");
		}
	});
	$([input_src[0], input_dst[0]]).focus(function() {
		$([input_src[0], input_dst[0]]).removeClass('grayed-out');
		input_src_dst.addClass('grayed-out');
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
		var uniqueid = $(this).parent().children('input[name="uniqueid"]');
		var calldate = $(this).parent().children('input[name="calldate"]');
		$("#listen").children('input[name="uniqueid"]').remove();
		$("#listen").children('input[name="calldate"]').remove();
		$("#listen").append(uniqueid);
		$("#listen").append(calldate);
	});
	$("#listen").on('shown', function() {
		var uniqueid = $(this).children('input[name="uniqueid"]').val();
		var calldate = $(this).children('input[name="calldate"]').val();
		$(".modal-body > p").load("/cdr/listen/" + uniqueid + "/" + calldate);
	});
	$("#listen").on('hidden', function() {
		var spinner = '<span class="spinner"></span>';
		$(".modal-body > p").html(spinner);
	});

});
