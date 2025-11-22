# WordPress Plugin Style Guide

This style guide outlines the coding conventions for WordPress plugin code. In general, the [coding standards for WordPress](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) should be followed:

* [CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
* [HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)
* [JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
* [PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

Note that for the JavaScript Coding Standards, the code should also be formatted using Prettier, specifically the [wp-prettier](https://www.npmjs.com/package/wp-prettier) fork with the `--paren-spacing` option which inserts many extra spaces inside parentheses.

For the HTML Coding Standards, disregard the guidance that void/empty tags should be self-closing, such as `IMG`, `BR`, `LINK`, or `META`. This is only relevant for XML (XHTML), not HTML. So instead of `<br />` this should only use `<br>`, for example.

Additionally, the [inline documentation standards for WordPress](https://developer.wordpress.org/coding-standards/inline-documentation-standards/) should be followed:

* [PHP Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/)
* [JavaScript Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/)

## Indentation

In general, indentation should use tabs. Refer to `.editorconfig` in the project root for specifics.

## Inline Documentation

It is expected for code introduced in pull requests to have `@since` tags with the `n.e.x.t` placeholder version. It will get replaced with the actual version at the time of release. Do not add any code review comments such code.

Every file, function, class, method constant, and global variable must have an associated docblock with a `@since` tag.

## PHP

Whenever possible, the most specific PHP type hints should be used, when compatible with the minimum version of PHP supported by WordPress, unless the `testVersion` config in `phpcs.xml.dist` is higher.

When native PHP type cannot be used, PHPStan's [PHPDoc Types](https://phpstan.org/writing-php-code/phpdoc-types) should be used, including not only the basic types but also subtypes like `non-empty-string`, [integer ranges](https://phpstan.org/writing-php-code/phpdoc-types#integer-ranges), [general arrays](https://phpstan.org/writing-php-code/phpdoc-types#general-arrays), and especially [array shapes](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes). The types should comply with PHPStan's level 10. 

The one exception for using PHP types is whenever a function is used as a filter. Since plugins can supply any value at all when filtering, it is important for the filtered value to always be `mixed`. The first statement in the function in this case must always check the type, and if it is not the expected type, override it to be so. For example:

```php
/**
 * Filters foo.
 *
 * @param string|mixed $foo Foo.
 * @return string Foo.
 */
function filter_foo( $foo, int $post_id ): string {
	if ( ! is_string( $foo ) ) {
		$foo = '';
	}
	/**
	 * Because plugins do bad things.
	 *
	 * @var string $foo
	 */

	 // Filtering logic goes here.

	 return $foo;
}
add_filter( 'foo', 'filter_foo', 10, 2 );
```

All PHP files should have a namespace which coincides with the `@package` tag in the file's PHPDoc header.

## JavaScript

All JavaScript code should be written with JSDoc comments. All function parameters, return values, and other types should use [TypeScript in JSDoc](https://www.typescriptlang.org/docs/handbook/jsdoc-supported-types.html).

JavaScript code is written using ES modules. This JS code must be runnable as-is without having to go through a build step, so it must be plain JavaScript and not TypeScript. The project _may_ also distribute minified versions of these JS files.

Never render HTML `script` markup directly. Always use the relevant APIs in WordPress for adding scripts, including `wp_enqueue_script()`, `wp_add_inline_script()`, `wp_localize_script()`, `wp_print_script_tag()`, `wp_print_inline_script_tag()`, `wp_enqueue_script_module()` among others. Since script modules are used, new scripts should normally have a `type="module"` when printing via `wp_print_inline_script_tag()` and when an external script is used, then `wp_enqueue_script_module()` is preferred.
