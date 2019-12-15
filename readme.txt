=== Syntax-highlighting Code Block (with Server-side Rendering) ===
Contributors: westonruter, mkaz
Tags: block, code, code syntax, syntax highlight, code highlighting
Requires at least: 5.2
Tested up to: 5.3
Stable tag: 1.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 5.6

Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.

== Description ==

This plugin extends to Code block in WordPress core to add syntax highlighting which is rendered on the server. By performing the syntax highlighting on the server, there is then no need to enqueue any JavaScript on the frontend (e.g. Highlight.js or Prism.js) and this ensures there is no flash of unhighlighted code (FOUC?). Reducing script on the frontend improves frontend performance and it also allows for the syntax highlighted code to appear properly in AMP pages as rendered by the [official AMP plugin](https://amp-wp.org) (see also [ampproject/amp-wp#972](https://github.com/ampproject/amp-wp/issues/972)).

The extended Code block uses auto-detection for the language in the block to add syntax highlighting, but you can override the language in the block inspector. (There is currently no syntax highlighting of the Code block in the editor.) The plugin supports for all [185 programming languages](https://highlightjs.org/static/demo/) that [highlight.php](https://github.com/scrivo/highlight.php) supports (being a port of [highlight.js](https://highlightjs.org/)).

Any one of [89 styles](https://highlightjs.org/static/demo/) may be enqueued that highlight.php/highlight.js provide. To change the `default` style, you may do so by picking a theme via Customizer in the Colors section. To override the `default` style programmatically, use the `syntax_highlighting_code_block_style` to supply the one of the [style names](https://github.com/scrivo/highlight.php/tree/master/styles), for example `github`:

<pre lang=php>
add_filter(
	'syntax_highlighting_code_block_style',
	function() {
		return 'github';
	}
);
</pre>

When a filter is provided, the theme selector in Customizer is automatically disabled.

This plugin is [developed on GitHub](https://github.com/westonruter/syntax-highlighting-code-block). See [list of current issues](https://github.com/westonruter/syntax-highlighting-code-block/issues) with the plugin. Please feel free to file any additional issues or requests that you may come across. [Pull requests](https://github.com/westonruter/syntax-highlighting-code-block/pulls) are welcome to help extend.

= Credits =

This is a fork of [Code Syntax Block](https://github.com/mkaz/code-syntax-block) by [Marcus Kazmierczak](https://mkaz.blog/) (mkaz), which is also [available on WordPress.org](https://wordpress.org/plugins/code-syntax-block/). Copyright (c) 2018 Marcus Kazmierczak. Licensed under GPL 2.0 or later.

[highlight.php](https://github.com/scrivo/highlight.php) is released under the BSD 3-Clause License. Copyright © 2006-2013, Ivan Sagalaev (maniac@softwaremaniacs.org), highlight.js (original author). Copyright © 2013, Geert Bergman (geert@scrivo.nl), highlight.php

== Screenshots ==

1. Supply content as you do normally in the Code block, optionally overriding the auto-detected language.
2. The Code block renders with syntax highlighting on the frontend without any JavaScript enqueued.

== Changelog ==

For the plugin’s changelog, please see [the Releases page on GitHub](https://github.com/westonruter/syntax-highlighting-code-block/releases).
