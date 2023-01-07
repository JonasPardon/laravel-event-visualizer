<?php

$finder = Symfony\Component\Finder\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        'no_whitespace_in_blank_line' => true,
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
        ],
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'single_quote' => true,
        'constant_case' => true,
        'lowercase_keywords' => true,
        'lowercase_static_reference' => true,
        'native_function_casing' => true,
        'native_function_type_declaration_casing' => true,
        'no_useless_else' => true,
        'function_declaration' => true,
        'function_typehint_space' => true,
        'return_type_declaration' => [
            'space_before' => 'none',
        ],
        'single_import_per_statement' => true,
        'list_syntax' => [
            'syntax' => 'short',
        ],
        'clean_namespace' => true,
        'logical_operators' => true,
        'new_with_braces' => true,
        'ternary_to_null_coalescing' => true,
        'no_closing_tag' => true,
        'array_indentation' => true,
        'compact_nullable_typehint' => true,
        'no_spaces_around_offset' => true,
        'blank_line_after_opening_tag' => false,
    ])
    ->setFinder($finder);
