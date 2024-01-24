# StellarWP Coding Standards

This package contains a base set of coding standards for StellarWP package development.

The rules are generally based on [the PSR-12 coding standard](https://www.php-fig.org/psr/psr-12/), which is generally recommended within the larger PHP community.

Additionally, since StellarWP is largely-focused on WordPress, our standards also include important WordPress-oriented rules (e.g. late-escaping of output, input sanitization, nonce usage, etc.).

## What's included?

There are two main tools included in this package:

### PHP_CodeSniffer

[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) is the de-facto linting tool for PHP, and there are a number of pre-configured standards available.

This package includes PHP_CodeSniffer itself, along with [the WordPress Coding Standards ruleset](https://github.com/WordPress/WordPress-Coding-Standards), [PHP compatibility checks](https://github.com/PHPCompatibility/PHPCompatibility), and [Dealerdirect's Composer installer for PHP_CodeSniffer](https://github.com/DealerDirect/phpcodesniffer-composer-installer).

### PHP-CS-Fixer

[PHP-CS-Fixer](https://cs.symfony.com/) is an additional coding standards checker for PHP, maintained by several members of the Symfony team. It provides a bit more flexibility around more sophisticated checks and experiemental features.

## Installation

This package should be installed as a development dependency for your project:

```sh
$ composer require --dev stellarwp/coding-standards
```

This will automatically expose the `phpcs`, `phpcbf`, and `php-cs-fixer` binaries in your project's `vendor/bin` directory.

You may also wish to add the following Composer scripts to make it easier to run checks:

```jsonc
# composer.json
{
    // ...
    "scripts": {
        // ...
        "test:standards": [
            "phpcs --standard=StellarWP --cache ./src ./tests",
            "php-cs-fixer fix --config=vendor/stellarwp/coding-standards/src/php-cs-fixer.php -v --diff --dry-run"
        ],
        "test:standards-fix": [
            "phpcbf --standard=StellarWP ./src ./tests",
            "php-cs-fixer fix --config=vendor/stellarwp/coding-standards/src/php-cs-fixer.php -v --diff"
        ]
    },
    "scripts-descriptions": [
        "test:standards": "Check coding standards.",
        "test:standards-fix": "Attempt to fix coding standards violations automatically.",
    ]
}
```

> **Note:** You may need to adjust paths to suit your project, especially if [your Composer "vendor-dir" has been changed](https://getcomposer.org/doc/06-config.md#vendor-dir).

## Project-specific configuration

The default coding standards make a few assumptions about the project (all of which may be overwritten on a per-project basis):

* Code should be compatible with PHP 5.6 or newer (to match [WordPress' minimum requirements](https://wordpress.org/about/requirements/))
* Code should be compatible with the latest and previous major release WordPress (a.k.a. "current minus one")
* Anything passed through [WordPress' internationalization (i18n) functions](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) should use the "stellarwp" text domain

Rather than overwriting these values via command-line arguments, it's recommended to create a PHP_CodeSniffer configuration file in your project. [An annotated starter configuration](src/stubs/phpcs.xml) is included in this package, and can automatically be copied into the project root (`.phpcs.xml.dist`) by running the following command:

```sh
$ vendor/bin/make-phpcs-config
```

Should you need to change the rules for PHP-CS-Fixer, you may publish a `.php-cs-fixer.dist.php` file by running the following:

```sh
$ vendor/bin/make-php-cs-fixer-config
```

If you've copied the default `test:standards` and `test:standards-fix` scripts into your `composer.json` file, **please be sure to update their arguments!**

```jsonc
# composer.json
{
    // ...
    "scripts": {
        // ...
        "test:standards": [
            "phpcs --cache",
            "php-cs-fixer fix --config=.php-cs-fixer.dist.php -v --diff --dry-run"
        ],
        "test:standards-fix": [
            "phpcbf",
            "php-cs-fixer fix --config=.php-cs-fixer.dist.php -v --diff"
        ]
    },
}
```

## License

Copyright © 2022 StellarWP

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
