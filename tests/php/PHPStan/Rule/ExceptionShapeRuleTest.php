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

/**
 * @extends ArchitectureRuleTestCase<ExceptionShapeRule>
 */
final class ExceptionShapeRuleTest extends ArchitectureRuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Errors/';

	private const EXCEPTIONS = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception';

	private const MARKER = self::EXCEPTIONS . '\PluginException';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Rule: docs/architecture-rules.md#architectureexceptionshape';

	protected function getRule(): Rule {
		return new ExceptionShapeRule(
			new PluginNamespace(
				'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors',
				[ 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\TestDouble' ]
			),
			self::MARKER,
			self::EXCEPTIONS,
			[ self::EXCEPTIONS . '\Helper' ]
		);
	}

	public function test_it_reports_exceptions_out_of_shape(): void {
		$this->analyse(
			$this->fixtures(),
			[
				[
					'Exception ' . self::EXCEPTIONS . '\NoNamedConstructor has no named constructor. Add a public'
						. ' static method that states the cause and returns self, such as from_path() or'
						. ' for_circular_reference(), and build its message there.',
					9,
					self::TIP,
				],
				[
					'Exception ' . self::EXCEPTIONS . '\NotSpl does not extend an SPL exception. Extend the one'
						. ' that matches its category, such as InvalidArgumentException for invalid input or'
						. ' RuntimeException for a failure while running, so callers can catch it without knowing'
						. ' the plugin.',
					9,
					self::TIP,
				],
				[ $this->not_an_exception_message( 'NotAnException' ), 7, self::TIP ],
				[ $this->not_an_exception_message( 'NotAMarker' ), 7, self::TIP ],
				[
					'Exception MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Elsewhere\MisplacedException is'
						. ' outside ' . self::EXCEPTIONS . '. Move it to ' . self::EXCEPTIONS . ', where every'
						. ' exception of the plugin lives.',
					10,
					self::TIP,
				],
			]
		);
	}

	/**
	 * Get the fixtures, in the order of the expected errors.
	 *
	 * @return array<string>
	 */
	private function fixtures(): array {
		$files = [
			'Exception/PluginException',
			'Exception/PluginLogicException',
			'Exception/Helper',
			'Exception/GoodException',
			'Exception/NoNamedConstructor',
			'Exception/NotSpl',
			'Exception/NotAnException',
			'Exception/NotAMarker',
			'Elsewhere/MisplacedException',
			'TestDouble/FakeException',
		];

		return array_map(
			static fn( string $file ): string => self::FIXTURES . $file . '.php',
			$files
		);
	}

	private function not_an_exception_message( string $name ): string {
		return sprintf(
			'%1$s\%2$s is in %1$s but does not implement %3$s. Only the plugin\'s exceptions live there:'
			. ' implement %3$s, or move it out of %1$s.',
			self::EXCEPTIONS,
			$name,
			self::MARKER
		);
	}
}
