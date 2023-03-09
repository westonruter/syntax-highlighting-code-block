module.exports = {
	"composer.*": [
		() => "composer --no-interaction validate --no-check-all",
		() => "npm run lint:composer"
	],
	"package.json": [
		"npm run lint:pkg-json"
	],
	"**/*.js": [
		"npm run lint:js"
	],
	"**/*.php": [
		"npm run lint:php",
		() => 'npm run lint:phpstan'
	]
};
