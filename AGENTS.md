# Agents

## Project Overview

This project is a WordPress plugin that extends the core `core/code` block to add server-side syntax highlighting. It uses the `scrivo/highlight.php` library (a PHP port of `highlight.js`) to render the highlighted code on the server.

This approach has several key benefits:
- **Performance:** It avoids the need to load a heavy JavaScript syntax highlighting library on the frontend, which improves page load times.
- **No FOUC:** It prevents a "flash of un-highlighted code" because the code is already highlighted when the page is served.
- **Compatibility:** It works in environments where JavaScript may be disabled, and is fully compatible with AMP (Accelerated Mobile Pages).

The plugin's administrative interface is built with JavaScript (React) as a standard Gutenberg block modification. It filters the existing `core/code` block to add a custom settings panel in the editor's sidebar, allowing users to select the language, specify lines to highlight, and toggle line numbers and wrapping.

**Key Technologies:**
- **PHP:** For server-side logic, including the actual syntax highlighting and integration with WordPress.
- **JavaScript (React/Gutenberg):** For enhancing the block editor interface.
- **@wordpress/scripts:** Used as the primary tool for building, linting, and running the development environment.
- **@wordpress/env:** Used for creating a local WordPress development environment.
- **Composer:** For managing PHP dependencies.
- **NPM:** For managing JavaScript dependencies and running scripts.

Please also see the [style guide](./.gemini/styleguide.md).

## Building and Running

### 1. Installation

First, install the necessary PHP and JavaScript dependencies.

```bash
composer install
npm install
```

### 2. Running the Local Development Environment

This project uses `@wordpress/env` to create a local WordPress instance for development.

- **Start the environment:**
  ```bash
  npm run wp-env start
  ```
- **Stop the environment:**
  ```bash
  npm run wp-env stop
  ```

Once started, the local site will be available at `http://localhost:8888`.
- **WordPress Admin:** `http://localhost:8888/wp-admin`
- **Username:** `admin`
- **Password:** `password`

### 3. Development

To watch for changes in the JavaScript files (`src/*.js`) and automatically re-compile them during development, run the following command:

```bash
npm run start
```

### 4. Building for Production

To create a production build of the JavaScript and other assets, run:

```bash
npm run build
```

To create a full distributable version of the plugin, including a `.zip` file ready for installation, run:

```bash
npm run build:dist
```
This will create a `syntax-highlighting-code-block.zip` file.

## Development Conventions

The project adheres to the official WordPress coding standards for both PHP and JavaScript.

### PHP

- **Standard:** WordPress Coding Standards (`WordPress-Core`, `WordPress-Extra`).
- **Linter:** `phpcs` (PHP_CodeSniffer)
- **Static Analysis:** `phpstan`
- **Commands:**
  - **Check for issues:** `npm run lint:php` or `composer phpcs`
  - **Automatically fix issues:** `npm run lint:php:fix` or `composer phpcbf`
  - **Run static analysis:** `npm run lint:phpstan` or `composer analyze`

### JavaScript

- **Standard:** `@wordpress/eslint-plugin/recommended`.
- **Linter:** ESLint
- **Commands:**
	- **Check for issues:** `npm run lint:js`
	- **Automatically fix issues:** `npm run lint:js:fix`

All linting tasks can be run at once with `npm run lint`.
