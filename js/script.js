const pdfRedirect = '/apps/emlviewer/ajax/pdf.php';
let tablesBinded = 0;
let callBind = setInterval(bindTable, 2500);

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
	var overlayStyle="position: fixed;z-index: -1;max-width: none;width: 100vw;height: 100vh;top: 0;left: 0;background-color: rgba(255, 255, 255, 0.91);-webkit-filter: blur(0px);-moz-filter: blur(0px);-o-filter: blur(0px);-ms-filter: blur(0px);filter: blur(0px);"
	var defaultHtml = '<div class="overlay overlay-blured" style="'+overlayStyle+'"></div><a class="close icon-close" href="#"></a>';
	var styles = 'position: fixed;z-index: 999999;max-width: none;width: 100vw;height: 100vh;top: 0;left: 0;background-color:rgba(0, 0, 0, 0);';
	defaultHtml += '<div class="mail-content" style="display: flex;height: 98%;max-width: 768px;margin-left: auto;margin-right: auto;text-align: left;padding-left: 25px;padding-right: 25px;width: 100%;flex-direction: column;">Preparing preview... please wait.</div>';

	$("#app-sidebar").remove();
	$("#app-content").after('<div id="app-sidebar" style="'+styles+'"></div>');
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
		redirectPost: function(location, args){
			var form = $('<form></form>');
			form.attr("method", "post");
			form.attr("action", location);
	
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
	$.redirectPost(pdfRedirect, {eml_file: encodeURI(file)});
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
			url: '/apps/emlviewer/ajax/emlparse.php',
			data: { eml_file: encodeURI(file) },
			success: function(response) {
				$(".mail-content").html(response);
				$("#toggle-text-content").click(() => { $("#email-text-content").toggleClass("fade-out");  $('#toggle-text-content').toggleText('Show content', 'Hide content'); });
				$("button#make-pdf").click(() => { buildPdf(file); });
			}
		});
	} else {
		$(".mail-content").text("Error retrieving the eml file. Try again later.");
	}
}