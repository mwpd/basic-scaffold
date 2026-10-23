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
 * @extends RuleTestCase<OutputOnlyThroughViewsRule>
 */
final class OutputOnlyThroughViewsRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/OutputOnlyThroughViews/';

	private const ROOT = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews';

	private const TIP = 'Principle 7: Show the alternative to WordPress anti-patterns, see '
		. 'docs/design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns. '
		. 'Pattern: docs/patterns.md#i-need-to-print-output. '
		. 'Rule: docs/architecture-rules.md#architectureoutputonlythroughviews';

	private string $root = self::ROOT;

	private string $infrastructure = 'MWPD\BasicScaffold\Infrastructure';

	/**
	 * @var array<string>
	 */
	private array $excluded = [];

	/**
	 * @var array<string>
	 */
	private array $allowed = [];

	protected function getRule(): Rule {
		return new OutputOnlyThroughViewsRule(
			new PluginNamespace( $this->root, $this->excluded ),
			$this->infrastructure,
			[ (string) realpath( self::FIXTURES . 'views' ) ],
			$this->allowed,
			$this->createReflectionProvider()
		);
	}

	public function test_it_reports_output_that_does_not_come_from_a_view(): void {
		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			array_map(
				fn( int $line ): array => $this->error( self::ROOT . '\Service', $line ),
				[ 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 27, 28 ]
			)
		);
	}

	public function test_it_allows_typed_views_renderables_and_factory_chains(): void {
		$this->analyse( [ self::FIXTURES . 'rendered.php' ], [] );
	}

	public function test_it_reports_inline_html_in_a_namespaced_class_file(): void {
		$this->analyse(
			[ self::FIXTURES . 'inline.php' ],
			[ $this->error( 'inline.php', 8 ) ]
		);
	}

	public function test_it_checks_file_scope_functions_and_closures(): void {
		$this->analyse(
			[ self::FIXTURES . 'functions.php' ],
			[
				$this->error( 'functions.php', 7 ),
				$this->error( 'functions.php', 10 ),
				$this->error( 'functions.php', 14 ),
			]
		);
	}

	public function test_classes_are_not_exempt_by_default(): void {
		$this->analyse(
			[ self::FIXTURES . 'AllowedPrinter.php' ],
			[ $this->error( self::ROOT . '\AllowedPrinter', 9 ) ]
		);
	}

	public function test_class_allowances_are_normalized(): void {
		$this->allowed = [ '\\' . strtoupper( self::ROOT . '\AllowedPrinter' ) ];
		$this->analyse( [ self::FIXTURES . 'AllowedPrinter.php' ], [] );
	}

	public function test_class_allowances_do_not_match_neighbouring_names(): void {
		// A neighbouring name does not inherit the allowance.
		$this->allowed = [ self::ROOT . '\AllowedPrinterOther' ];
		$this->analyse(
			[ self::FIXTURES . 'AllowedPrinter.php' ],
			[ $this->error( self::ROOT . '\AllowedPrinter', 9 ) ]
		);
	}

	public function test_it_ignores_other_namespaces_and_templates(): void {
		$this->analyse(
			[ self::FIXTURES . 'outside.php', self::FIXTURES . 'views/template.php' ],
			[]
		);
	}

	public function test_it_ignores_excluded_namespaces(): void {
		$this->excluded = [ self::ROOT ];
		$this->analyse( [ self::FIXTURES . 'Service.php', self::FIXTURES . 'inline.php' ], [] );
	}

	public function test_it_uses_the_configured_infrastructure_namespace(): void {
		$this->infrastructure = self::ROOT . '\Infrastructure';
		$this->analyse( [ self::FIXTURES . 'renamed.php' ], [] );
	}

	public function test_the_sample_backend_service_passes(): void {
		$this->root     = 'MWPD\BasicScaffold';
		$this->excluded = [ 'MWPD\BasicScaffold\Tests' ];
		$this->analyse( [ dirname( __DIR__, 4 ) . '/src/SampleSubsystem/SampleBackendService.php' ], [] );
	}

	/**
	 * @return array{string, int, string}
	 */
	private function error( string $owner, int $line ): array {
		return [
			'Output in ' . $owner . ' does not come directly from a Renderable or View method call.'
				. ' Render a view from an injected ViewFactory and echo its result.',
			$line,
			self::TIP,
		];
	}
}
