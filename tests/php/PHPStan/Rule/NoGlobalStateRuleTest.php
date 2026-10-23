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
 * @extends RuleTestCase<NoGlobalStateRule>
 */
final class NoGlobalStateRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/NoGlobalState/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState';

	private const FIX = ' Receive the value through the constructor; for collaborators such as $wpdb or WP_Query,'
		. ' configure a delegation in the composition root, as for WP_Post in src/BasicScaffoldPlugin.php.'
		. ' Read request input only in a class listed in parameters.architecture.noGlobalState.inputAdapters'
		. ' that sanitizes and normalizes it into an internal type.';

	private const TIP = 'Principle 7: Show the alternative to WordPress anti-patterns, see '
		. 'docs/design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns. '
		. 'Pattern: docs/patterns.md#i-need-wordpress-state-such-as-the-current-post. '
		. 'Rule: docs/architecture-rules.md#architecturenoglobalstate';

	protected function getRule(): Rule {
		return new NoGlobalStateRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			[ (string) realpath( self::FIXTURES . 'views' ) . '/' ],
			[ '\\' . strtoupper( self::FIXTURE_NAMESPACE . '\InputAdapter' ) ]
		);
	}

	public function test_it_reports_globals_and_every_request_superglobal_in_services(): void {
		$owner = self::FIXTURE_NAMESPACE . '\Service';
		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			array_merge(
				[ $this->error( 'Global statement', $owner, 9 ), $this->error( '$GLOBALS', $owner, 11 ) ],
				array_map(
					fn( string $name, int $line ): array => $this->error( $name, $owner, $line ),
					[ '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES', '$_ENV' ],
					range( 12, 18 )
				)
			)
		);
	}

	public function test_adapters_allow_input_but_never_globals(): void {
		$owner = self::FIXTURE_NAMESPACE . '\InputAdapter';
		$this->analyse(
			[ self::FIXTURES . 'InputAdapter.php' ],
			[ $this->error( 'Global statement', $owner, 14 ), $this->error( '$GLOBALS', $owner, 15 ) ]
		);
	}

	public function test_adapter_allowances_do_not_extend_to_subclasses(): void {
		$this->analyse(
			[ self::FIXTURES . 'ChildAdapter.php' ],
			[ $this->error( '$_GET', self::FIXTURE_NAMESPACE . '\ChildAdapter', 9 ) ]
		);
	}

	public function test_it_checks_namespaced_file_scope_functions_closures_and_anonymous_classes(): void {
		$this->analyse(
			[ self::FIXTURES . 'functions.php' ],
			[
				$this->error( 'Global statement', 'functions.php', 7 ),
				$this->error( '$_GET', 'functions.php', 8 ),
				$this->error( '$_POST', 'functions.php', 10 ),
				$this->error( '$_COOKIE', 'functions.php', 13 ),
				$this->error( '$_ENV', 'functions.php', 15 ),
				$this->error( '$_SERVER', 'class@anonymous', 18 ),
			]
		);
	}

	public function test_it_checks_views_including_functions_closures_and_writes(): void {
		$this->analyse(
			[ self::FIXTURES . 'views/input.php', self::FIXTURES . 'views/nested/input.php' ],
			[
				$this->error( 'Global statement', 'input.php', 3 ),
				$this->error( '$GLOBALS', 'input.php', 4 ),
				$this->error( '$_GET', 'input.php', 5 ),
				$this->error( '$_POST', 'input.php', 7 ),
				$this->error( '$_COOKIE', 'input.php', 10 ),
				$this->error( '$_ENV', 'input.php', 12 ),
				$this->error( '$_SERVER', 'input.php', 13 ),
				$this->error( '$GLOBALS', 'input.php', 14 ),
				$this->error( '$_FILES', 'input.php', 15 ),
				$this->error( '$_REQUEST', 'input.php', 3 ),
			]
		);
	}

	public function test_it_skips_unrelated_and_excluded_namespaces_and_sibling_view_directories(): void {
		$this->analyse(
			[
				self::FIXTURES . 'outside.php',
				self::FIXTURES . 'excluded.php',
				self::FIXTURES . 'views-other/outside.php',
			],
			[]
		);
	}

	public function test_it_allows_injected_values_wordpress_functions_and_unlisted_superglobals(): void {
		$this->analyse( [ self::FIXTURES . 'allowed.php' ], [] );
	}

	/**
	 * @return array{string, int, string}
	 */
	private function error( string $access, string $owner, int $line ): array {
		$problem = $access === 'Global statement'
			? sprintf( 'Global statement in %s pulls state from globals.', $owner )
			: ( $access === '$GLOBALS'
				? sprintf( '$GLOBALS in %s accesses global state.', $owner )
				: sprintf( 'Request input %s in %s bypasses an input adapter.', $access, $owner ) );

		return [ $problem . self::FIX, $line, self::TIP ];
	}
}
