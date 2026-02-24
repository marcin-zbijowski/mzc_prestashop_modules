<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        'blank_line_after_opening_tag' => false,
        'no_blank_lines_after_phpdoc' => true,
        'single_blank_line_before_namespace' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_indent' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'no_extra_blank_lines' => true,
        'indentation_type' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof' => true,
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],
    ])
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setFinder($finder);
