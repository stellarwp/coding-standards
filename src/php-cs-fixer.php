<?php

/**
 * Custom configuration for PHP-CS-Fixer.
 *
 * @link https://cs.symfony.com/
 */

$finder = PhpCsFixer\Finder::create()
    ->exclude('node_modules')
    ->in(getcwd());

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'class_definition' => [
            'space_before_parenthesis' => true,
        ],
        'comment_to_phpdoc' => false,
        'compact_nullable_typehint' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_equal_normalize' => [
            'space' => 'single',
        ],
        'dir_constant' => true,
        'ereg_to_preg' => true,
        'function_typehint_space' => true,
        'include' => true,
        'increment_style' => [
            'style' => 'post',
        ],
        'is_null' => true,
        'linebreak_after_opening_tag' => true,
        'lowercase_cast' => true,
        'mb_str_functions' => true,
        // This rule can cause issues with mixed PHP and HTML in templates.
        'method_argument_space' => false,
        'multiline_comment_opening_closing' => true,
        'native_function_casing' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => [
            'use' => 'echo',
        ],
        'no_null_property_initialization' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_in_blank_line' => true,
        'not_operator_with_successor_space' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'psr_autoloading' => true,
        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],
        'standardize_not_equals' => true,
        'strict_comparison' => true,
        'ternary_operator_spaces' => true,
        'visibility_required' => [
            'elements' => ['method', 'property'],
        ],
        'whitespace_after_comma_in_array' => true,
        'yoda_style' => true,
    ])
    ->setRiskyAllowed(true)
    ->setHideProgress(false);
