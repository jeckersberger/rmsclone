<?php
$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src/services',
        __DIR__ . '/src/api',
        __DIR__ . '/src/business',
        __DIR__ . '/src/cron',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(false);
