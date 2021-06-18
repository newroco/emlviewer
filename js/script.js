(function(OCA) {

	OCA.FilesEmlViewer = OCA.FilesEmlViewer || {};

	/**
	 * @namespace OCA.FilesEmlViewer.PreviewPlugin
	 */
	OCA.FilesEmlViewer.PreviewEml = {

		_baseUrl: '/apps/emlviewer',
		/**
		 * @param fileList
		 */
		attach: function(fileList) {
			this._extendFileActions(fileList.fileActions);
		},

		bringInSidebar: function() {
			let defaultHtml = '<div class="overlay"></div><a class="close icon-close" href="#"></a>';
			defaultHtml += '<div class="mail-content"><br/><br/>Preparing preview... please wait.</div>';

			$("#app-sidebar").remove();
			$("#app-content").after('<div id="app-sidebar" class="emlviewer"></div>');
			$("#app-sidebar").html(defaultHtml);
			$(".icon-close").unbind();
			$(".icon-close").click(function(){ $("#app-sidebar").remove(); });
		},
		/**
		 * @param filePath
		 */
		displayParsedEmail: function(filePath,shareToken) {
			if(!shareToken){
				shareToken = '';
			}
			const parserUrl = OC.generateUrl(OCA.FilesEmlViewer.PreviewEml._baseUrl + '/emlparse?eml_file={file}&share_token={token}', {file: filePath,token: shareToken});
			$.ajax({
				async: false,
				method: 'GET',
				url: parserUrl,
				success: function(response, e) {
					$(".mail-content").html(response);

					if($("#make-pdf").length > 0) {
						$("#toggle-text-content").click(() => {
							$("#email-text-content").toggleClass("fade-out");
							$('#toggle-text-content').toggleText('Show raw content', 'Hide raw content');
						});
					}
				},
				error: function(response) {
					$(".mail-content").html('Could not load data from server. ' + $(response.responseText).find('.error').html());
					console.log(response);
				}
			});
		},

		/**
		 * @param filePath
		 * @param shareToken
		 */
		show: function(filePath,shareToken) {
			this.bringInSidebar();
			this.displayParsedEmail(filePath,shareToken);
		},

		/**
		 * @param fileActions
		 * @private
		 */
		_extendFileActions: function(fileActions) {
			var self = this;
			if (typeof isSecureViewerAvailable !== "undefined" && isSecureViewerAvailable()) {
				return;
			}
			let sharingToken = $('#sharingToken').val();

			fileActions.registerAction({
				name: 'view',
				displayName: 'View',
				mime: 'application/octet-stream',
				permissions: OC.PERMISSION_READ,
				actionHandler: function(fileName, context) {
					if (fileName.trim().endsWith('.eml')) {
						self.show(context.dir + '/' + fileName,sharingToken);
					}
				}
			});
			fileActions.setDefault('application/octet-stream', 'view');
		}
	};

})(OCA);

OC.Plugins.register('OCA.Files.FileList', OCA.FilesEmlViewer.PreviewEml);

// FIXME: Hack for single public file view since it is not attached to the fileslist
$(document).ready(function(){
	if ($('#isPublic').val() && $('#mimetype').val() === 'application/octet-stream' && $('#filename').val().trim().endsWith('.eml')) {
		var sharingToken = $('#sharingToken').val();
		var viewer = OCA.FilesEmlViewer.PreviewEml;
		viewer.show('',sharingToken);//single shares don't require file path
	}
});
