
# Syntax-highlighted Code Block (with Server-side Rendering)

A WordPress plugin which extends Gutenberg by adding *server-side* syntax highlighting to the WordPress core code block. Fully compatible with the [offical AMP plugin](https://amp-wp.org).

Example:

<img src="screenshot.png" title="Screenshot example in use" alt="screen shot" width="554" height="202" style="border:1px solid #333">

### Usage

Install syntax-highlighted-code-block plugin to your WordPress plugins directory and activate. You can download a ZIP from the [GitHub](https://github.com/westonruter/syntax-highlighted-code-block), without any build step required. You can also run `npm run zip` to create a ZIP of the non-development files.

This plugin upgrades the existing Code block in core. It uses auto-detection for the language in the block to add syntad highlighting, but you can override the language in the block inspector.

On the front-end when the post is being viewed, the code will be color syntax highlighted. Syntax highighting is performed server-side via [highlight.php](https://github.com/scrivo/highlight.php), so there is no JavaScript required on the frontend (e.g. Prism.js). Because of this, AMP pages get the same syntax highlighting as non-AMP pages. 

### Customize

The default install uses a limited set of languages from highlight.php (bash, cpp, css, diff, go, javascript, json, markdown, php, python, sql, xml). If your language is not included, you can modify the [`.gitignore`] to skip ignoring them and then optionally add a label in the `get_languages()` PHP function.

For styling, the [default color theme](https://github.com/scrivo/highlight.php/blob/master/styles/default.css) is used from highlight.php. To use a different color scheme, you can use the `syntax_highlighted_code_block_style` filter to use another one of the [styles](https://github.com/scrivo/highlight.php/tree/master/styles):

```php
add_filter(
	'syntax_highlighted_code_block_style',
	function() {
		return 'github';
	}
);
```

### Colophon

- Uses [highlight.php syntax highlighter](https://github.com/scrivo/highlight.php)

### Contribute

See [list of current issues](https://github.com/westonruter/syntax-highlighted-code-block/issues) with the plugin. Please feel free to file any additional issues or requests that you may come across. [Pull requests](https://github.com/westonruter/syntax-highlighted-code-block/pulls) are welcome to help extend.

### License

Copyright © 2018 Weston Ruter.  
Forked from [mkaz/code-syntax-block](https://github.com/mkaz/code-syntax-block), copyright © 2018 Marcus Kazmierczak.  
Licensed under [GPL 2.0 or later](https://opensource.org/licenses/GPL-2.0).

[highlight.php](https://github.com/scrivo/highlight.php) is released under the BSD 3-Clause License.  
Copyright © 2006-2013, Ivan Sagalaev (maniac@softwaremaniacs.org ), highlight.js (original author).  
Copyright © 2013, Geert Bergman (geert@scrivo.nl), highlight.php
