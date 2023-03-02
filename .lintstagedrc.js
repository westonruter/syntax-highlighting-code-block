module.exports = {
	"composer.*": () => "npm run lint:composer",
	"package.json": [
		"npm run lint:pkg-json"
	],
	"**/*.js": [
		"npm run lint:js"
	],
	"**/*.php": [
		"npm run lint:php",
	],
	'*.php': () => 'npm run lint:phpstan'
};
