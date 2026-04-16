<?php

/**
 * PHP-CS-Fixer configuration for LibreMesh
 *
 * Uses PSR-12 coding standard with sensible overrides for this project.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor/')
    ->exclude('data/')
    ->exclude('.git/')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => [
            'operators' => ['=>' => 'single_space', '=' => 'single_space'],
        ],
        'blank_line_after_namespace' => true,
        'braces' => ['allow_single_line_empty_body' => false],
        'class_definition' => ['single_line' => false],
        'constant_case' => ['case' => 'lower'],
        'function_typehint_space' => true,
        'increment_style' => ['style' => 'post'],
        'no_blank_lines_after_phpdoc' => true,
        'no_closing_tag' => true,
        'no_extra_blank_lines' => ['tokens' => ['curly_open_block', 'extra']],
        'no_multiline_whitespace_around_curly_braces' => true,
        'no_php4_constructor' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_trailing_comma_in_list' => true,
        'no_unused_imports' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,
        'normalize_index_brace' => true,
        'phpdoc_indent' => true,
        'phpdoc_order' => ['order' => ['param', 'return', 'throws', 'see', 'since']],
        'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'return_type_declaration' => ['space' => 'one'],
        'self_accessor' => true,
        'single_trait_insert_per_statement' => true,
        'trailing_comma_in_multiline' => true,
        'visibility_required' => ['elements' => ['property', 'method', 'constant']],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');