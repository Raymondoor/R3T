<?php declare(strict_types=1);
use Rector\CodeQuality\Rector\If_\CompleteMissingIfElseBracketRector;
use Rector\CodingStyle\Rector\If_\AlternativeIfToBracketRector;
use Rector\Config\RectorConfig;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/test',
    ])
    ->withSkip([
        __DIR__.'/vendor',
        __DIR__.'/node_modules',
        __DIR__.'/script',
        NewlineBetweenClassLikeStmtsRector::class,
        NewlineAfterStatementRector::class,
        CatchExceptionNameMatchingTypeRector::class,
        RemoveAlwaysElseRector::class,
        CompleteMissingIfElseBracketRector::class,
        AlternativeIfToBracketRector::class,
    ])
    ->withPhpSets(php83:true)
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        deadCode: true,
        typeDeclarations: true,
    );
