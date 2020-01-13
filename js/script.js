$(document).ready(function(){
	if($("#filestable").length) { 
		$('#filestable').bind('DOMSubtreeModified', function(e) {
			$(".name[href$='.eml']").unbind();
			var emlFiles = $("#filestable tr .name[href$='.eml']");
			if (emlFiles.length > 0) {
				$(".name[href$='.eml']").on('click', function(e){
					e.preventDefault();
					bringInSidebar();
					displayParsedEmail($(this).attr('href'));
				});
				$(".name[href$='.pdf']").on('click', function(e){
					$('#app-sidebar').remove();
				});
			}
		});
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
				$("#toggle-text-content").click(() => { $("#email-text-content").toggleClass("fade-out"); });
			}
		});
	} else {
		$(".mail-content").text("Error retrieving the eml file. Try again later.");
	}
}
