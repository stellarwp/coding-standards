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

A hook's argument and return types are never guaranteed. Relying on documentation
that turns out to be inaccurate can lead to the wrong native type, and any third
party can dispatch one of your hooks via `apply_filters()` / `do_action()` with
arguments of a different type. Either way, a value that does not match a
**native parameter type** or a **native return type** declared on the handler
causes a runtime **fatal**. This standard enforces that with two complementary
tools, following the hook type:

- **Filter handlers:** no native type on **any** parameter and no native return
  type.
- **Action handlers:** no native type on **any** parameter; a native `void`
  return type is allowed (actions always return void).

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

The sniff is part of the `StellarWP` standard, so referencing it needs no
configuration. Enable just this sniff with:

```xml
<rule ref="StellarWP.Hooks.HookHandlerTypes"/>
```

An optional `allow_void_return_on_actions` property (default `true`) controls
whether a native `void` return type is permitted on action handlers.

### PHPStan rule

The rule ships as an auto-discovered PHPStan extension. Projects using
[`phpstan/extension-installer`](https://github.com/phpstan/extension-installer)
get it registered automatically with **no configuration** - no `includes:` entry
and no parameters required.

If you are not using `extension-installer`, include the extension manually:

```neon
includes:
    - vendor/stellarwp/coding-standards/StellarWP/PHPStan/extension.neon
```

The optional `allowVoidReturnOnActions` parameter (default `true`) mirrors the
sniff property:

```neon
parameters:
    stellarwpHookHandlerTypes:
        allowVoidReturnOnActions: true
```

When adopting this in a project with existing violations, regenerate your PHPStan
baseline to grandfather them, then burn them down over time.
