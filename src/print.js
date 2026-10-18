const bindPrintButton = () => {
	document.getElementById('printButton')?.addEventListener('click', () => window.print())
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bindPrintButton, { once: true })
} else {
	bindPrintButton()
}
