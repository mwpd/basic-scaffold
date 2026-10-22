<?php
/**
 * Configuration for shipmonk/composer-dependency-analyser.
 *
 * The plugin runs on PHP and WordPress alone (design principle 1), so the code
 * that runs in production may not use any package from require-dev. This only
 * checks that: the tests may use whatever they need, and WordPress functions
 * and classes are unknown to Composer.
 *
 * Run it through the architecture test suite, see docs/architecture-rules.md.
 *
 * @package MWPD\BasicScaffold
 */

declare( strict_types=1 );

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return ( new Configuration() )
	->disableComposerAutoloadPathScan()
	// Only one kind of error matters here, so the others are ignored everywhere.
	->disableReportingUnmatchedIgnores()
	->addPathToScan( __DIR__ . '/basic-scaffold.php', false )
	->addPathToScan( __DIR__ . '/src', false )
	->addPathToScan( __DIR__ . '/views', false )
	->ignoreErrors(
		[
			ErrorType::SHADOW_DEPENDENCY,
			ErrorType::UNUSED_DEPENDENCY,
			ErrorType::UNKNOWN_CLASS,
			ErrorType::UNKNOWN_FUNCTION,
			ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV,
		]
	);
