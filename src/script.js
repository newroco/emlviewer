/* eslint-disable jsdoc/require-param-description */
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { registerFileAction, DefaultType, Permission } from '@nextcloud/files'
import { isPublicShare, getSharingToken } from '@nextcloud/sharing/public'

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

		bringInSidebar() {
			const defaultHtml = `
                <span class="close icon-close"></span>
                <div class="mail-content"><br/><br/>Preparing preview... please wait.</div>
            `

			const appSidebar = document.getElementById('app-sidebar')
			if (appSidebar) {
				appSidebar.remove()
			}

			let appContent = document.getElementById('content-vue')
			if(!appContent){
				appContent = document.getElementById('content')
			}

			const newSidebar = document.createElement('div')
			newSidebar.id = 'app-sidebar'
			newSidebar.className = 'emlviewer'
			newSidebar.innerHTML = defaultHtml
			if (appContent) {
				appContent.after(newSidebar)
			} else {
				document.body.appendChild(newSidebar)
			}

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
					const toggleTextContentButton = document.getElementById('toggle-text-content')
					if (toggleTextContentButton) {
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


	}

})(OCA)


// OC.Plugins.register('OCA.Files.FileList', OCA.FilesEmlViewer.PreviewEml)

function isEmlNode(node) {
	if (!node) {
		return false
	}

	const basename = (node.basename || '').toLowerCase()
	const path = (node.path || '').toLowerCase()

	return node.mime === 'message/rfc822'
		|| basename.endsWith('.eml')
		|| path.endsWith('.eml')
}

function registerEmlFileAction() {
	let sharingToken = ''
	if (isPublicShare()) {
		sharingToken = getSharingToken()
	}

	const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/></svg>`
	registerFileAction({
		id: 'eml_view',
		displayName: () => t('view', 'View'),
		default: DefaultType.DEFAULT,
		order: 0,
		mime: 'message/rfc822',
		enabled: ({ nodes }) => {
			try {
				if (!Array.isArray(nodes) || nodes.length === 0) {
					return false
				}

				return nodes.every((node) => isEmlNode(node) && Boolean((node?.permissions ?? 0) & Permission.READ))
			} catch (error) {
				console.error(error)
				return false
			}
		},
		iconSvgInline: () => {return svg},
		permissions: OC.PERMISSION_READ,
		async exec({ nodes }) {
			const node = nodes?.[0]
			if (!node?.path) {
				return false
			}

			OCA.FilesEmlViewer.PreviewEml.show(node.path, sharingToken)
			return true
		},
	})
}

registerEmlFileAction()
