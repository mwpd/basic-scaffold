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
 * @extends RuleTestCase<QueryContextNeedsDelayRule>
 */
final class QueryContextNeedsDelayRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Hooks/';

	private const HOOKS = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks';

	private string $root = self::HOOKS;

	/**
	 * Query-dependent functions, including a mixed-case configuration entry.
	 *
	 * @var array<string>
	 */
	private array $query_functions = [ 'IS_SINGULAR' ];

	/**
	 * Namespaces outside the rule's scope.
	 *
	 * @var array<string>
	 */
	private array $excluded = [];

	protected function getRule(): Rule {
		return new QueryContextNeedsDelayRule(
			new PluginNamespace( $this->root, $this->excluded ),
			$this->root . '\Infrastructure',
			$this->query_functions,
			$this->createReflectionProvider()
		);
	}

	public function test_query_conditions_require_delayed(): void {
		$this->analyse(
			[
				self::FIXTURES . 'Feature/QueryConditional.php',
				self::FIXTURES . 'Feature/DelayedQueryConditional.php',
				self::FIXTURES . 'Feature/AdminConditional.php',
				self::FIXTURES . 'Feature/NotConditional.php',
				self::FIXTURES . 'Feature/IndirectQueryConditional.php',
			],
			[ $this->error( 'is_singular', 'QueryConditional' ) ]
		);
	}

	public function test_only_configured_functions_require_delayed(): void {
		$this->query_functions = [ 'is_admin' ];

		$this->analyse(
			[
				self::FIXTURES . 'Feature/QueryConditional.php',
				self::FIXTURES . 'Feature/AdminConditional.php',
			],
			[ $this->error( 'is_admin', 'AdminConditional' ) ]
		);
	}

	public function test_excluded_namespaces_are_not_checked(): void {
		$this->excluded = [ self::HOOKS . '\Feature' ];

		$this->analyse( [ self::FIXTURES . 'Feature/QueryConditional.php' ], [] );
	}

	public function test_the_sample_loop_service_passes(): void {
		// The sample uses the real interfaces rather than the fixture ones.
		$this->root     = 'MWPD\BasicScaffold';
		$this->excluded = [ 'MWPD\BasicScaffold\Tests' ];

		$this->analyse( [ dirname( __DIR__, 4 ) . '/src/SampleSubsystem/SampleLoopService.php' ], [] );
	}

	/**
	 * @return array{string, int, string}
	 */
	private function error( string $called, string $class_name ): array {
		return [
			$called . '() in ' . self::HOOKS . '\\Feature\\' . $class_name . '::is_needed() asks about the query before'
				. ' it is available. Implement Delayed and return "wp" or a later action from'
				. ' get_registration_action(), so the plugin waits for the query before checking is_needed().',
			12,
			'Principle 7: Show the alternative to WordPress anti-patterns, see'
				. ' docs/design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns.'
				. ' Pattern: docs/patterns.md#i-need-to-hook-in-later.'
				. ' Rule: docs/architecture-rules.md#architecturequerycontextneedsdelay',
		];
	}
}
