const path = require('path')
// we extend the Nextcloud webpack config
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

// this is the rule with which the /src/mainScript.js file will be compiled to /js/catgifs-mainScript.js
const appId = 'emlviewer'
webpackConfig.entry = {
	main: { import: path.join(__dirname, 'src', 'script.js'), filename: appId + '-script.js' },
}
module.exports = webpackConfig
