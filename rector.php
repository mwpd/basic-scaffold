<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/php/Fixture/views/broken-view.php',
        RemoveUnusedVariableAssignRector::class => [
            __DIR__ . '/tests/php/Unit/SimpleViewTest.php',
        ],
    ])
    ->withIndent('  ', 4)
    ->withImportNames(true, true, true, true)
    ->withSets([
        SetList::PHP_52,
        SetList::PHP_53,
        SetList::PHP_54,
        SetList::PHP_55,
        SetList::PHP_56,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::CODE_QUALITY,
        SetList::STRICT_BOOLEANS,
        SetList::PRIVATIZATION,
        SetList::CODING_STYLE,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
    ]);
