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
 * @extends RuleTestCase<ExceptionsKeepCauseRule>
 */
final class ExceptionsKeepCauseRuleTest extends RuleTestCase {

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\KeepCause';

	private const FIXTURE = __DIR__ . '/../Fixture/KeepCause/Service.php';

	/**
	 * Namespaces outside the rule's scope.
	 *
	 * @var array<string>
	 */
	private array $excluded = [];

	private string $root = self::FIXTURE_NAMESPACE;

	protected function getRule(): Rule {
		return new ExceptionsKeepCauseRule(
			new PluginNamespace( $this->root, $this->excluded ),
			// Class and method names match as PHP does, including a leading slash.
			[ '\\' . strtoupper( self::FIXTURE_NAMESPACE ) . '\\SERVICE::ALLOWED' ]
		);
	}

	public function test_translated_exceptions_keep_the_caught_exception(): void {
		$this->analyse(
			[ self::FIXTURE ],
			[
				$this->translation_error( 'translate', 'caught', 16 ),
				$this->translation_error( 'create', 'caught', 25 ),
				[
					'Empty catch in ' . self::FIXTURE_NAMESPACE . '\\Service::discard() discards the caught exception.'
						. ' Handle the failure or throw an exception with the caught exception as its previous cause;'
						. ' list reviewed recoveries in parameters.architecture.exceptionsKeepCause.emptyCatches.',
					57,
					$this->tip(),
				],
				$this->translation_error( 'nested', 'inner', 77 ),
			]
		);
	}

	public function test_excluded_namespaces_are_not_checked(): void {
		$this->excluded = [ self::FIXTURE_NAMESPACE ];

		$this->analyse( [ self::FIXTURE ], [] );
	}

	public function test_other_namespaces_are_not_checked(): void {
		$this->root = 'AnotherPlugin';

		$this->analyse( [ self::FIXTURE ], [] );
	}

	/**
	 * @return array{string, int, string}
	 */
	private function translation_error( string $method, string $caught, int $line ): array {
		return [
			'Throw in ' . self::FIXTURE_NAMESPACE . '\\Service::' . $method . '() loses the caught exception $'
				. $caught . '. Pass $' . $caught . ' to the exception constructor or named constructor as the'
				. ' previous cause, or rethrow $' . $caught . '.',
			$line,
			$this->tip(),
		];
	}

	private function tip(): string {
		return 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
			. 'Pattern: docs/patterns.md#i-need-to-report-a-failure. '
			. 'Rule: docs/architecture-rules.md#architectureexceptionskeepcause';
	}
}
