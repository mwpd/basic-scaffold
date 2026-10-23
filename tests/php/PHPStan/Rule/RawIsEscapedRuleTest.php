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
 * @extends RuleTestCase<RawIsEscapedRule>
 */
final class RawIsEscapedRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/RawEscaping/';

	private const TIP = 'Principle 9: Never trust what WordPress hands over, see '
		. 'docs/design-principles.md#9-never-trust-what-wordpress-hands-over. '
		. 'Pattern: docs/patterns.md#i-need-to-print-output. '
		. 'Rule: docs/architecture-rules.md#architecturerawisescaped';

	/**
	 * @var array<string>
	 */
	private array $escaping_functions = [
		'esc_html',
		'esc_attr',
		'esc_url',
		'esc_js',
		'esc_textarea',
		'esc_xml',
		'wp_kses',
		'wp_kses_post',
		'wp_kses_data',
		'absint',
		'intval',
	];

	protected function getRule(): Rule {
		return new RawIsEscapedRule(
			[
				(string) realpath( self::FIXTURES . 'views' ) . '/',
				dirname( __DIR__, 4 ) . '/views',
			],
			$this->escaping_functions
		);
	}

	public function test_it_reports_raw_output_in_echo_short_echo_and_print(): void {
		$this->analyse(
			[ self::FIXTURES . 'views/unescaped.php' ],
			array_map(
				fn( int $line ): array => $this->error( 'unescaped.php', $line ),
				[ 3, 5, 7, 9, 10, 11, 12, 13, 14, 16, 18, 19, 20, 22, 26, 27, 28, 30, 32 ]
			)
		);
	}

	public function test_it_allows_escaped_subexpressions_and_escaped_property_access(): void {
		$this->analyse( [ self::FIXTURES . 'views/escaped.php' ], [] );
	}

	public function test_it_uses_the_configured_functions_instead_of_a_fixed_list(): void {
		$this->escaping_functions = [ '\\STRTOUPPER' ];

		$this->analyse(
			[ self::FIXTURES . 'views/custom.php' ],
			[ $this->error( 'custom.php', 4 ) ]
		);
	}

	public function test_it_only_checks_configured_view_directories(): void {
		$this->analyse(
			[ self::FIXTURES . 'outside.php', self::FIXTURES . 'views-other/outside.php' ],
			[]
		);
	}

	public function test_the_production_templates_escape_their_raw_values(): void {
		$views = dirname( __DIR__, 4 ) . '/views/';

		$this->analyse( [ $views . 'test-backend-service.php', $views . 'test-loop-service.php' ], [] );
	}

	/**
	 * @return array{string, int, string}
	 */
	private function error( string $template, int $line ): array {
		return [
			sprintf(
				'Template %1$s, line %2$d, prints an unescaped raw() value. Wrap it in a context-appropriate'
				. ' esc_*(), wp_kses*(), absint() or intval() function listed in'
				. ' parameters.architecture.rawIsEscaped.escapingFunctions.',
				$template,
				$line
			),
			$line,
			self::TIP,
		];
	}
}
