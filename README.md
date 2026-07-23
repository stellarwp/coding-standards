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
phpcs --config-set installed_paths "$(phpcs --config-show | grep installed_paths | awk '{ print $2 }'),${HOME}/.composer/vendor/phpcsstandards/phpcsextra,${HOME}/.composer/vendor/phpcsstandards/phpcsutils,${HOME}/.composer/vendor/wp-coding-standards/wpcs,${HOME}/.composer/vendor/automattic/vipwpcs,${HOME}/.composer/vendor/stellarwp/coding-standards,${HOME}/.composer/vendor/sirbrillig/phpcs-variable-analysis,${HOME}/.composer/vendor/slevomat/coding-standard"

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

## Hook handler argument types

WordPress passes hook arguments with no type guarantees and reassigns or discards
filter return values however it likes. Declaring a **native parameter type** or a
**native return type** on a handler attached to a hook you do not own (a WP core
hook, or one defined by another plugin or theme) can therefore cause a runtime
**fatal** when the value received - or the value the filter chain expects back -
does not match the declared type. The safe posture is to be type-less on those
handlers.

This standard enforces that rule with two complementary tools. "First-party" is
determined solely by prefixes you configure, which should mirror your project's
`WordPress.NamingConventions.PrefixAllGlobals` value. Any hook whose name does
**not** start with a configured prefix is treated as WP core / third-party and its
handler must be type-less.

| | PHPCS sniff (`StellarWP.Hooks.HookHandlerTypes`) | PHPStan rule |
|---|---|---|
| Runs in | phpcs (fast, in-editor) | phpstan (whole codebase, never diff-limited) |
| Covers | same-file handlers only (inline closures/arrows, `[ $this, 'method' ]` / `[ self::class, 'method' ]`, and `'function_name'`) with literal hook names | all callback forms across files (via reflection) and hook names that type inference narrows to constant string(s) |
| Auto-fix | yes (`phpcbf` strips the offending native types) | no (report-only; the message names the exact handler) |

Run both: the sniff gives instant, auto-fixable feedback for the common
same-file case, while the PHPStan rule is the authoritative gate that catches
handlers whose declaration lives in a different file from the `add_filter()` /
`add_action()` call - the case a diff-limited phpcs run can miss.

### PHPCS sniff

The sniff is part of the `StellarWP` standard. Configure the `prefixes` property
(and, optionally, `allow_void_return_on_actions`, default `true`). With no
prefixes configured the sniff does nothing.

```xml
<rule ref="StellarWP.Hooks.HookHandlerTypes">
    <properties>
        <property name="prefixes" type="array">
            <element value="my_project"/>
            <element value="mp_"/>
        </property>
    </properties>
</rule>
```

### PHPStan rule

The rule ships as an auto-discovered PHPStan extension. Projects using
[`phpstan/extension-installer`](https://github.com/phpstan/extension-installer)
get it registered automatically - no `includes:` entry needed. You must still set
the `prefixes` parameter in your `phpstan.neon` (an empty list makes the rule a
no-op):

```neon
parameters:
    stellarwpHookHandlerTypes:
        prefixes:
            - my_project
            - mp_
        # allowVoidReturnOnActions: true  # optional, default true
```

If you are not using `extension-installer`, include the extension manually:

```neon
includes:
    - vendor/stellarwp/coding-standards/StellarWP/PHPStan/extension.neon
```

> The `prefixes` value now lives in up to three places - `PrefixAllGlobals` in
> `phpcs.xml`, the sniff's `prefixes`, and the rule's `prefixes` in `phpstan.neon`.
> There is no shared source both tools can read, so keep them in sync.

When adopting this in a project with existing violations, regenerate your PHPStan
baseline to grandfather them, then burn them down over time.
