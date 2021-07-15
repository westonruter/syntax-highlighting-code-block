/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * External dependencies
 */
import { memoize } from 'lodash';

const { customize } = global.wp;

const themeNameCustomizeId = 'syntax_highlighting[theme_name]';
const lineColorCustomizeId =
	'syntax_highlighting[highlighted_line_background_color]';

/**
 * Init.
 *
 * @param {Object} args
 * @param {wp.customize.Control} args.themeNameControl
 * @param {wp.customize.Control} args.lineColorControl
 */
function init({ themeNameControl, lineColorControl }) {
	const colorPickerElement =
		lineColorControl.container.find('.color-picker-hex');

	themeNameControl.setting.bind(async (newThemeName) => {
		const isColorCustomized =
			lineColorControl.setting().toLowerCase() !==
			lineColorControl.params.defaultValue.toLowerCase();

		lineColorControl.params.defaultValue = await getDefaultThemeLineColor(
			newThemeName
		);

		// Make sure the default value gets propagated into the wpColorPicker.
		colorPickerElement.wpColorPicker(
			'defaultColor',
			lineColorControl.params.defaultValue
		);

		// Update the color to be the default if it was not customized.
		if (!isColorCustomized) {
			lineColorControl.setting.set(lineColorControl.params.defaultValue);
		}
	});
}

/**
 * Get default theme line color.
 *
 * @param {string} themeName
 * @return {Promise} Promise.
 */
const getDefaultThemeLineColor = memoize((themeName) => {
	return apiFetch({
		path: `/syntax-highlighting-code-block/v1/highlighted-line-background-color/${themeName}`,
	});
});

// Initialize once the controls are available.
customize.control(
	themeNameCustomizeId,
	lineColorCustomizeId,
	(themeNameControl, lineColorControl) => {
		init({
			themeNameControl,
			lineColorControl,
		});
	}
);
