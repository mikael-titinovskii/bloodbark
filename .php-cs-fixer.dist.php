<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    // PHP that arrives with the JS toolchain, and anything else npm unpacks.
    ->exclude('node_modules')
    // Written by `codecept build`, not by hand.
    ->exclude('tests/Support/_generated')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
        '#^tests/Support/\w+Tester\.php$#',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
