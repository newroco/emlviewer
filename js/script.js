let tablesBinded = 0;
let callBind = setInterval(bindTable, 2500);
const baseUrl = OC.generateUrl('/apps/emlviewer');
const pdfRedirect = baseUrl + '/pdf';

function bindTable() {
	$(".list-container:visible").each(function(){
		var $this = $(this);
		tablesBinded = 1;
		$this.find(".name[href$='.eml']").off('click');
		$this.find(".name[href$='.eml']").on('click', function(e){
			e.preventDefault();
			bringInSidebar();
			displayParsedEmail($(this).attr('href'));
		});
		$this.find(".name[href$='.pdf']").on('click', function(e){
			$('#app-sidebar').remove();
		});
	});
}

$(document).ready(function(){
	if($("#filestable").length) { 
		bindTable();
	}

	if (tablesBinded == 1) {
		clearInterval(callBind);
		callBind = null;
	}
});

function bringInSidebar() {
	var defaultHtml = '<div class="overlay"></div><a class="close icon-close" href="#"></a>';
	defaultHtml += '<div class="mail-content"><br/><br/>Preparing preview... please wait.</div>';

	$("#app-sidebar").remove();
	$("#app-content").after('<div id="app-sidebar" class="emlviewer"></div>');
	$("#app-sidebar").html(defaultHtml);
	$(".icon-close").unbind();
	$(".icon-close").click(function(){ $("#app-sidebar").remove(); });
}

$.fn.extend({
    toggleText: function(a, b){
        return this.text(this.text() == b ? a : b);
	}
});

$.extend({
		redirectPost: function(location, args,blank = false){
			var form = $('<form></form>');
			form.attr("method", "post");
			form.attr("action", location);
			if(blank){
				form.attr("target", '_blank');
			}
	
			$.each( args, function( key, value ) {
				var field = $('<input></input>');
	
				field.attr("type", "hidden");
				field.attr("name", key);
				field.attr("value", value);
	
				form.append(field);
			});
			$(form).appendTo('body').submit();
		}
});
	

function buildPdf(file) {
	$.redirectPost(pdfRedirect, {eml_file: encodeURI(file)},true);
}

function displayParsedEmail(emailFile) {
	var file = '';
	
	$.ajax({
		async: false,
		method: 'GET',
		url: emailFile,
		success: function(response) {
			file = response;
		}
	});
	
	if (file.length) {
		$.ajax({
			async: false,
			method: 'POST',
			url: baseUrl + '/emlparse',
			data: { eml_file: encodeURI(file) },
			success: function(response, e) {
				$(".mail-content").html(response);

				if($("button#make-pdf").length > 0) {
					$("#toggle-text-content").click(() => {
						$("#email-text-content").toggleClass("fade-out");
						$('#toggle-text-content').toggleText('Show raw content', 'Hide raw content');
					});
					$("button#make-pdf").click(() => {
						buildPdf(file);
					});
				}
			},
			error: function(response) {
				$(".mail-content").html('Could not load data from server. Error: ' + response);
			}
		});
	} else {
		$(".mail-content").text("Error retrieving the eml file. Try again later.");
	}
}