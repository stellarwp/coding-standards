Tribal Scents
==================

Modern Tribe standards for PHP CodeSniffer

The Modern Tribe coding standards uses a combination of:
* Generic (part of PHP_CodeSniffer)
* PEAR (part of PHP_CodeSniffer)
* PSR2 (part of PHP_CodeSniffer)
* Squiz (part of PHP_CodeSniffer)
* Zend (part of PHP_CodeSniffer)
* Custom sniffs (a few based on [WordPress](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards))


## Important Notes

Make sure that the command `phpcs` is on the version `2.9.0`, as it's not compatible with the latest version of the script. In order to install it on that specifc version globally you can use: 

-- `composer global require "squizlabs/php_codesniffer=2.9.0"`

## Setup on PHPStorm

You can follow [this guide](https://confluence.jetbrains.com/display/PhpStorm/PHP+Code+Sniffer+in+PhpStorm#PHPCodeSnifferinPhpStorm-4.1.Obtainingcustomcodestyles) the only step you can replace is the one on **Installing via Composer** by the one above to install the 2.9.0 version instead.
