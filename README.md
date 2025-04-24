# StellarWP Coding Standards for PHP CodeSniffer

StellarWP coding standards uses a combination of:
* Generic (part of PHP_CodeSniffer)
* PEAR (part of PHP_CodeSniffer)
* PSR2 (part of PHP_CodeSniffer)
* Squiz (part of PHP_CodeSniffer)
* Zend (part of PHP_CodeSniffer)
* Custom sniffs (a few based on [WordPress](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards))

> [!IMPORTANT]  
> Make sure that the command `phpcs` is on version `3.4.2`+. In order to install it on that specifc version globally you can use: 
> ```sh
> composer global require "squizlabs/php_codesniffer=3.4.2"
> ```

## Complete Installation Script

For a full installation of all required components, you can use the following script. This will install all necessary packages globally and configure PHPCS to use them:

```bash
#!/bin/bash

# Install PHP_CodeSniffer
composer global require "squizlabs/php_codesniffer=^3.8.0"

# Install required coding standards and dependencies
composer global require "wp-coding-standards/wpcs:^3.0.0"
composer global require "automattic/vipwpcs:^3.0"
composer global require "phpcsstandards/phpcsextra:*"
composer global require "phpcsstandards/phpcsutils:*"
composer global require "stellarwp/coding-standards:*"
composer global require "sirbrillig/phpcs-variable-analysis:*"
composer global require "slevomat/coding-standard:^8.14.0"

# Update PHPCS installed paths to include all standards
phpcs --config-set installed_paths "$(phpcs --config-show | grep installed_paths | awk '{ print $2 }'),${HOME}/.config/composer/vendor/phpcsstandards/phpcsextra,${HOME}/.config/composer/vendor/phpcsstandards/phpcsutils,${HOME}/.config/composer/vendor/wp-coding-standards/wpcs,${HOME}/.composer/vendor/automattic/vipwpcs,${HOME}/.composer/vendor/stellarwp/coding-standards,${HOME}/.composer/vendor/sirbrillig/phpcs-variable-analysis,${HOME}/.composer/vendor/slevomat/coding-standard"

# Verify installation
echo "Installed PHPCS version:"
phpcs --version
echo ""
echo "Installed coding standards:"
phpcs -i
```

> [!NOTE]
> The script assumes your global Composer binaries are in your PATH. Adjust the paths in the script if your Composer global installation directory is different.

## Setup on PHPStorm

You can follow [this guide](https://confluence.jetbrains.com/display/PhpStorm/PHP+Code+Sniffer+in+PhpStorm#PHPCodeSnifferinPhpStorm-4.1.Obtainingcustomcodestyles) the only step you can replace is the one on **Installing via Composer** by the one above to install the 3.4.2 version instead.

## Example Usage via `phpcs.xml` File

```xml
<?xml version="1.0"?>
<ruleset name="StellarWP Coding Standards">
    <rule ref="WordPress-VIP-Go"/>
    <rule ref="WordPress-Docs">
        <exclude-pattern>*/tests/*</exclude-pattern>
    </rule>
    <rule ref="WordPress">
        <exclude name="WordPress.Files.FileName"/>
        <exclude name="Generic.Arrays.DisallowShortArraySyntax"/>
    </rule>
    <rule ref="StellarWP"/>
    
    <!--exclude the rule for violation of direct DB calls as some have no alternative-->
    <rule ref="WordPress.DB.DirectDatabaseQuery">
        <exclude-pattern>src/Test.php</exclude-pattern>
    </rule>
    
    <exclude-pattern>*/tests/_support/_generated/*</exclude-pattern>
    <exclude-pattern>*/vendor/*</exclude-pattern>
</ruleset>
```
