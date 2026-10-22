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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * Base for the tests of the architecture rules.
 *
 * It loads the parser visitors the rules rely on. Fixtures that refer to each
 * other's classes follow PSR-4, because PHPStan's test case only finds
 * classes it can autoload.
 *
 * @template TRule of Rule
 * @extends RuleTestCase<TRule>
 */
abstract class ArchitectureRuleTestCase extends RuleTestCase {

	/**
	 * Get the configuration files the tests need on top of PHPStan's own.
	 *
	 * @return array<string> Paths of the configuration files.
	 */
	public static function getAdditionalConfigFiles(): array {
		return array_merge(
			parent::getAdditionalConfigFiles(),
			[ dirname( __DIR__ ) . '/visitors.neon' ]
		);
	}
}
