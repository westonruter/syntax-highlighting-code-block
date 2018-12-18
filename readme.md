
# Server-Side Code Syntax Highlighting Block

A WordPress plugin which extends Gutenberg by adding *server-side* syntax highlighting to the WordPress core code block.

Example:

<img src="screenshot.png" title="Screenshot example in use" alt="screen shot" width="554" height="202" style="border:1px solid #333">

### Usage

Install code-syntax-block plugin to your WordPress plugins directory and activate. You can download a ZIP from the [GitHub](https://github.com/westonruter/code-syntax-block), without any build step required. You can also run `npm run zip` to create a ZIP of the non-development files.

This plugin upgrades the existing Code block in core. It uses auto-detection for the language in the block to add syntad highlighting, but you can override the language in the block inspector.

On the front-end when the post is being viewed, the code will be color syntax highlighted.

### Customize

The default install uses a limited set of languages from highlight.php (bash, cpp, css, diff, go, javascript, json, markdown, php, python, sql, xml). If your language is not included, you can modify the [`.gitignore`] to skip ignoring them and then optionally add a label in the `get_languages()` PHP function.

Changing color theme, the [default color theme](https://github.com/scrivo/highlight.php/blob/master/styles/default.css) is used from highlight.php. To use a different color scheme, just download one of the [other styles](https://github.com/scrivo/highlight.php/tree/master/styles) and then dequeue the default CSS to replace with your own.

### Colophon

- Uses [highlight.php syntax highlighter](https://github.com/scrivo/highlight.php)

### Contribute

See [list of current issues](https://github.com/westonruter/code-syntax-block/issues) with the plugin. Please feel free to file any additional issues or requests that you may come across. [Pull requests](https://github.com/westonruter/code-syntax-block/pulls) are welcome to help extend.

### License

Copyright © 2018 Weston Ruter.  
Forked from [mkaz/code-syntax-block](https://github.com/mkaz/code-syntax-block), copyright © 2018 Marcus Kazmierczak.  
Licensed under [GPL 2.0 or later](https://opensource.org/licenses/GPL-2.0).

[highlight.php](https://github.com/scrivo/highlight.php) is released under the BSD 3-Clause License.  
Copyright © 2006-2013, Ivan Sagalaev (maniac@softwaremaniacs.org ), highlight.js (original author).  
Copyright © 2013, Geert Bergman (geert@scrivo.nl), highlight.php
