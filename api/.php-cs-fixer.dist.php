<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        // Every file refuses silent type conversions: an int parameter takes an int, nothing else
        'declare_strict_types' => true,
    ])
    ->setFinder($finder)
;
