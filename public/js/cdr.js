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

    if (input_src.length > 0) {

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

    }

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
		var uniqueid = $(this).parent().children('input[name="uniqueid"]').val();
		var calldate = $(this).parent().children('input[name="calldate"]').val();
		$("#listen").data("uniqueid", uniqueid);
		$("#listen").data("calldate", calldate);
	});
	$("#listen").on('shown', function() {
		var uniqueid = $(this).data("uniqueid");
		var calldate = $(this).data("calldate");
		$(".modal-body > p").load("/cdr/listen/" + uniqueid + "/" + calldate);
	});
	$("#listen").on('hidden', function() {
		var spinner = '<span class="spinner"></span>';
		$(".modal-body > p").html(spinner);
	});

	$(".note-add-button").click(function() {
		var uniqueid = $(this).parent().children('input[name="uniqueid"]');
		var cdr_info = $(this).parent().children('input[name="cdr_info"]').val();
		var uniqueid_class = $(this).parent().attr("class");

		$(".note-form").children('input[name="uniqueid"]').remove();
		$(".note-form").append(uniqueid.clone());
		$(".note-form").attr("id", "note-add-form");
		$(".note-form").attr("action", "/note/add");
		$(".note-form").attr("data-uniqueid_class", uniqueid_class);
		$('#note-message').addClass("alert-info").text('Yeni not ekleniyor.');
		$('#note-modal-title').text(cdr_info);
		$('.note-submit-button').attr("form", "note-add-form");
	});

	$(".note-edit-button").click(function() {
		var uniqueid = $(this).parent().children('input[name="uniqueid"]');
		var cdr_info = $(this).parent().children('input[name="cdr_info"]').val();
		var uniqueid_value = uniqueid.val();
		var uniqueid_class = $(this).parent().attr("class");

		$(".note-form").children('input[name="uniqueid"]').remove();
		$(".note-form").append(uniqueid.clone());
		$("#note-delete-confirm-form").children('input[name="uniqueid"]').remove();
		$("#note-delete-confirm-form").append(uniqueid.clone());
		$(".note-form").attr("id", "note-edit-form");
		$(".note-form").attr("action", "/note/edit/" + uniqueid_value);
		$("#note-delete-confirm-form").attr("action", "/note/delete/");
		$("#note-delete-confirm-form").attr("data-uniqueid_class", uniqueid_class);
		$(".note-form").attr("data-uniqueid_class", uniqueid_class);
		$('#note-message').addClass("alert-info");
		$('#note-modal-title').text(cdr_info);
		$('.note-submit-button').attr("form", "note-edit-form");
		$('#note-delete-button').removeClass('hide');

		$.getJSON("/note/info/" + uniqueid_value, function(data){
			$('#note-textarea').val(data.note);
			$('#note-message').html(data.info);
		});
	});

	$('#note-delete-button').click(function() {
		$('.note-form').addClass('hide');
		$('#note-message').addClass('hide');
		$('#note-delete-confirm-form').removeClass('hide');
		$('#note-delete-confirm-message').removeClass('hide').removeClass('alert-danger').removeClass('alert-success').addClass('alert-warning').text('Bu notu silmek istediğinizden emin misiniz?');
		$('#note-modal .modal-footer').addClass('hide');
	});

	$('#note-delete-confirm-no').click(function(){
		$('.note-form').removeClass('hide');
		$('#note-message').removeClass('hide');
		$('#note-delete-confirm-form').addClass('hide');
		$('#note-delete-confirm-message').addClass('hide');
		$('#note-modal .modal-footer').removeClass('hide');
	});

	$("#note-modal").on('shown', function() {
		$("#note-textarea").focus();
	});

	$("#note-modal").on('hidden', function() {
		$('#note-message').removeClass("alert-success").removeClass("alert-danger").removeClass("alert-info").html('');
		$('#note-textarea').val('');
		$('.note-submit-button').removeAttr('form');
		$('.note-form').removeAttr("data-uniqueid_class");
		$('#note-delete-button').addClass('hide');
		$('.note-form').removeClass('hide');
		$('#note-message').removeClass('hide');
		$('#note-delete-confirm-form').addClass('hide');
		$('#note-delete-confirm-message').addClass('hide');
		$('#note-delete-confirm-form').removeAttr("data-uniqueid_class");
		$('#note-modal .modal-footer').removeClass('hide');
	});

	$('.note-form').on('submit', function(event) {
        var $form = $(this);
        var $target = $($form.attr('data-target'));

        $.ajax({
            type: $form.attr('method'),
            url: $form.attr('action'),
            data: $form.serialize(),
            dataType: "json",

            success: function(data) {
            	$target.removeClass("alert-info").removeClass("alert-danger").addClass("alert-" + data.alert);
                $target.html(data.message);

                if (data.alert == 'success') {
                	if ($form.attr('id') == 'note-add-form') {
            			$('.' + $form.attr("data-uniqueid_class") + ' .note-add-button').addClass('hide');
            			$('.' + $form.attr("data-uniqueid_class") + ' .note-edit-button').removeClass('hide');
            		}
                	$('#note-modal .modal-footer').addClass('hide');
                	setTimeout(function(){
                		$('#note-modal').modal('hide')
                	}, 1000);
                }
            }
        });

        event.preventDefault();
    });

	$('#note-delete-confirm-form').on('submit', function(event) {
        var $form = $(this);
        var $target = $($form.attr('data-target'));

        $.ajax({
            type: $form.attr('method'),
            url: $form.attr('action'),
            data: $form.serialize(),
            dataType: "json",

            success: function(data) {
            	$target.removeClass("alert-info").removeClass("alert-danger").removeClass("alert-warning").addClass("alert-" + data.alert);
                $target.html(data.message);

                if (data.alert == 'success') {
           			$('.' + $form.attr("data-uniqueid_class") + ' .note-edit-button').addClass('hide');
            		$('.' + $form.attr("data-uniqueid_class") + ' .note-add-button').removeClass('hide');
                	$('#note-delete-confirm-form').addClass('hide');
                	setTimeout(function(){
                		$('#note-modal').modal('hide')
                	}, 1000);
                }
            }
        });

        event.preventDefault();
    });

});
