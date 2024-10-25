/* eslint-disable jsdoc/require-param-description */
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
// import { registerFileAction, getFileActions } from '@nextcloud/files'

(function(OCA) {

	OCA.FilesEmlViewer = OCA.FilesEmlViewer || {}

	OCA.FilesEmlViewer.toggleText = function(elem, a, b) {

		elem.textContent = elem.textContent === b ? a : b
	}

	/**
	 * @namespace OCA.FilesEmlViewer.PreviewPlugin
	 */

	OCA.FilesEmlViewer.PreviewEml = {

		_baseUrl: '/apps/emlviewer',

		/**
		 * @param fileList
		 */

		attach(fileList) {
			this._extendFileActions(fileList.fileActions)
		},

		bringInSidebar() {
			const defaultHtml = `
                <span class="close icon-close"></span>
                <div class="mail-content"><br/><br/>Preparing preview... please wait.</div>
            `

			const appSidebar = document.getElementById('app-sidebar')

			if (appSidebar) {

				appSidebar.remove()
			}

			const appContent = document.getElementById('app-content')

			const newSidebar = document.createElement('div')
			newSidebar.id = 'app-sidebar'
			newSidebar.className = 'emlviewer'
			newSidebar.innerHTML = defaultHtml
			appContent.after(newSidebar)

			const closeIcon = document.querySelector('.icon-close')

			if (closeIcon) {
				closeIcon.addEventListener('click', () => {

					newSidebar.remove()
				})
			}
		},

		/**
		 * @param filePath
		 */

		displayParsedEmail(filePath, shareToken = '') {
			const parserUrl = generateUrl(OCA.FilesEmlViewer.PreviewEml._baseUrl + '/emlparse?eml_file={file}&share_token={token}', {
				file: filePath,
				token: shareToken,
			})
			axios.get(parserUrl)
				.then((response) => {
					const mailContentElement = document.querySelector('.mail-content')
					if (mailContentElement) {
						mailContentElement.innerHTML = response.data
					}

					const makePdfElement = document.getElementById('make-pdf')
					if (makePdfElement) {
						const toggleTextContentButton = document.getElementById('toggle-text-content')

						toggleTextContentButton.addEventListener('click', () => {

							const emailTextContentElement = document.querySelector('.emlviewer_email_text_content')
							if (emailTextContentElement) {
								emailTextContentElement.classList.toggle('fade-out')
							}
							OCA.FilesEmlViewer.toggleText(toggleTextContentButton, 'Show raw content', 'Hide raw content')
						})

					}
				})
				.catch((error) => {

					const mailContentElement = document.querySelector('.mail-content')

					if (mailContentElement) {
						mailContentElement.innerHTML = 'Could not load data from server.'
					}
					console.error(error)
				})
		},

		/**
		 *
		 * @param {string} filePath
		 * @param {string} [shareToken]
		 */
		show(filePath, shareToken) {
			this.bringInSidebar()
			this.displayParsedEmail(filePath, shareToken)

		},

		/**
		 *
		 * @param {object} fileActions
		 *
		 */
		_extendFileActions(fileActions) {

			if (typeof isSecureViewerAvailable !== 'undefined' && isSecureViewerAvailable()) {
				return
			}

			const sharingToken = document.getElementById('sharingToken') ? document.getElementById('sharingToken').value : ''

			fileActions.registerAction({
				name: 'view',
				displayName: t('emlviewer', 'View'),
				mime: 'application/octet-stream',
				permissions: OC.PERMISSION_READ,
				actionHandler(fileName, context, event) {
					event.preventDefault()
					if (fileName.trim().toLowerCase().endsWith('.eml')) {
						OCA.FilesEmlViewer.PreviewEml.show(context.dir + '/' + fileName, sharingToken)
					}

				},
			})

			fileActions.setDefault('application/octet-stream', 'view')

			fileActions.registerAction({
				name: 'view',
				displayName: t('emlviewer', 'View'),
				mime: 'message/rfc822',
				permissions: OC.PERMISSION_READ,
				actionHandler(fileName, context, event) {
					event.preventDefault()
					OCA.FilesEmlViewer.PreviewEml.show(context.dir + '/' + fileName, sharingToken)
				},
			})
			fileActions.setDefault('message/rfc822', 'view')
		},
	}

})(OCA)

function isSecureViewerAvailable() {
	return true
}

// OC.Plugins.register('OCA.Files.FileList', OCA.FilesEmlViewer.PreviewEml)

document.addEventListener('DOMContentLoaded', function() {

	/* registerFileAction({ // inregistrez ca e de tip .eml
		name: 'view',
		displayName: 'View',
		mime: 'message/rfc822',
		permissions: OC.PERMISSION_READ,
		actionHandler(fileName, context, event) {
			event.preventDefault()
			OCA.FilesEmlViewer.PreviewEml.show(context.dir + '/' + fileName, '')
		},
	})

	const fileActions = getFileActions() // setare implicita (default)
	const emlAction = fileActions.find(action => action.mime === 'message/rfc822')

	if (emlAction) {
		emlAction.defaultAction = 'view'
	}
 */

})
