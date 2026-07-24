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

Removing the native types does **not** remove the need for type safety - it moves
that responsibility into the callback. Native types gave you a guarantee the
handler could rely on; since that guarantee is not safe here, the handler has to
provide it itself. Validate the incoming arguments (and, for filters, the value
you return) at the top of the handler before using them.

Check the type before you use it - do not simply cast. A blind cast is not safe:
`$value` could be an object or array, and `(string) $value` would mangle it or
fatal. Use a scalar/type check (`is_string()`, `is_numeric()`, `is_scalar()`, ...)
and handle the unexpected case. For a filter, return the value unchanged when it
is not something you can handle, so the chain is preserved:

```php
// Do not rely on the signature to guarantee the types:
//   public function filter_the_value( string $value, int $post_id ): string
public function filter_the_value( $value, $post_id ) {
    // A cast alone is unsafe - $value could be an object or array.
    if ( ! is_string( $value ) ) {
        return $value; // not something we handle: pass it through unchanged.
    }

    $post_id = is_numeric( $post_id ) ? (int) $post_id : 0;

    // ... $value is a string and $post_id is an int now.

    return $value;
}
```

| | PHPCS sniff (`StellarWP.Hooks.HookHandlerTypes`) | PHPStan rule |
|---|---|---|
| Runs in | phpcs (fast, in-editor) | phpstan (whole codebase, never diff-limited) |
| Covers | same-file handlers only (inline closures/arrows, `[ $this, 'method' ]` / `[ self::class, 'method' ]`, `'function_name'`, and `$this->add_action( 'tag', 'method' )` wrappers) with literal hook names | all callback forms across files (via reflection), including `$container->callback( Class::class, 'method' )` and wrapper methods like `$this->add_action( 'tag', 'method' )`, plus hook names that type inference narrows to constant string(s) |
| Auto-fix | yes (`phpcbf` strips the offending native types), except `$this->add_action()` wrapper handlers, which are reported but not auto-fixed (see below) | no (report-only; the message names the exact handler) |

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

#### Wrapper handlers are reported but not auto-fixed

Handlers registered through a `$this->add_action( 'tag', 'method' )` wrapper (for
example memberdash's `MS_Hooker`, where the second argument names a method on the
enclosing class and falls back to the hook name when it is absent or empty) are
resolved with a name-match heuristic: the sniff finds the enclosing-class method
whose name matches that argument. That heuristic is safe enough to **report** on,
but not to **auto-fix** - in rare cases the matched method may not actually be a
hook handler (for instance a class that defines its own unrelated
`add_action()` / `add_filter()` method alongside a coincidentally-named method),
and stripping native types is destructive.

So for the wrapper form the sniff raises the violation without a fix: it still
fails CI, but `phpcbf` will not touch it. Remove the native types by hand, or, if
it is a false positive, silence it at the handler with an ignore annotation:

```php
// phpcs:ignore StellarWP.Hooks.HookHandlerTypes.NativeParameterType
public function my_handler( int $post_id ): void {}
```

Direct callbacks (`[ $this, 'method' ]`, closures, arrow functions, same-file
global functions) are unambiguous and remain auto-fixable as usual.

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
