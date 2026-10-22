<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * Configures composer-dependency-analyser to look for packages that the
 * production code uses but may not rely on.
 *
 * The plugin runs on PHP and WordPress alone (design principle 1), so the
 * code that runs in production may neither use a package from require-dev,
 * nor one that is only installed because another package needs it. The
 * tests may use whatever they need, and WordPress's own functions and
 * classes are unknown to Composer, so neither counts.
 */
final class RuntimeDependencyConfiguration {

	/**
	 * Build the configuration for a repository.
	 *
	 * @param RepositoryLayout $layout Layout of the repository.
	 * @return Configuration Configuration of the analyser.
	 */
	public static function for_layout( RepositoryLayout $layout ): Configuration {
		return ( new Configuration() )
			->disableComposerAutoloadPathScan()
			->addPathsToScan( $layout->get_runtime_paths(), false )
			// Only packages the production code uses matter here.
			->disableReportingUnmatchedIgnores()
			->ignoreErrors(
				[
					ErrorType::UNUSED_DEPENDENCY,
					ErrorType::UNKNOWN_CLASS,
					ErrorType::UNKNOWN_FUNCTION,
					ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV,
				]
			);
	}
}
