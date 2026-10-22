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
 * The rules that share the architecture.noWordPressErrorMechanisms
 * identifier, each tested against the same fixtures.
 *
 * @phpstan-type TestedRule NoErrorReportingFunctionsRule|NoWordPressErrorCreationRule|NoWordPressErrorInMethodsRule
 *     |NoWordPressErrorInFunctionsRule|NoWordPressErrorInClosuresRule|NoWordPressErrorInArrowFunctionsRule
 *     |NoWordPressErrorInPropertiesRule
 * @extends ArchitectureRuleTestCase<TestedRule>
 */
final class NoWordPressErrorMechanismsRuleTest extends ArchitectureRuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Errors/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Rule: docs/architecture-rules.md#architecturenowordpresserrormechanisms';

	/**
	 * Rule under test.
	 *
	 * @var TestedRule
	 */
	private Rule $rule;

	protected function getRule(): Rule {
		return $this->rule;
	}

	public function test_it_reports_wordpress_error_functions(): void {
		$this->rule = new NoErrorReportingFunctionsRule( $this->plugin_namespace(), $this->createReflectionProvider() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->function_message( 'error_log' ), 23, self::TIP ],
				[ $this->function_message( 'trigger_error' ), 24, self::TIP ],
				[ $this->function_message( '_doing_it_wrong' ), 25, self::TIP ],
			]
		);
	}

	public function test_it_reports_creating_a_wp_error(): void {
		$this->rule = new NoWordPressErrorCreationRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'new WP_Error() carries an error through the plugin.' ), 29, self::TIP ],
			]
		);
	}

	public function test_it_reports_methods_that_take_or_return_a_wp_error(): void {
		$this->rule = new NoWordPressErrorInMethodsRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'Method %s\Service::fail() takes or returns a WP_Error.' ), 28, self::TIP ],
			]
		);
	}

	public function test_it_reports_functions_that_take_or_return_a_wp_error(): void {
		$this->rule = new NoWordPressErrorInFunctionsRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'Function %s\last_error() takes or returns a WP_Error.' ), 9, self::TIP ],
			]
		);
	}

	public function test_it_reports_closures_that_take_or_return_a_wp_error(): void {
		$this->rule = new NoWordPressErrorInClosuresRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'A closure takes or returns a WP_Error.' ), 67, self::TIP ],
			]
		);
	}

	public function test_it_reports_arrow_functions_that_take_or_return_a_wp_error(): void {
		$this->rule = new NoWordPressErrorInArrowFunctionsRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'An arrow function takes or returns a WP_Error.' ), 70, self::TIP ],
			]
		);
	}

	public function test_it_reports_properties_that_hold_a_wp_error(): void {
		$this->rule = new NoWordPressErrorInPropertiesRule( $this->transport() );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->transport_message( 'Property %s\Service::$last_error holds a WP_Error.' ), 15, self::TIP ],
				[ $this->transport_message( 'Property %s\Service::$result holds a WP_Error.' ), 20, self::TIP ],
			]
		);
	}

	private function plugin_namespace(): PluginNamespace {
		return new PluginNamespace( self::FIXTURE_NAMESPACE );
	}

	private function transport(): WordPressErrorTransport {
		return new WordPressErrorTransport( $this->plugin_namespace(), [ self::FIXTURE_NAMESPACE . '\Adapter' ] );
	}

	/**
	 * @return array<string>
	 */
	private function fixtures(): array {
		return [
			self::FIXTURES . 'wp-error.php',
			self::FIXTURES . 'Service.php',
			self::FIXTURES . 'Adapter.php',
			self::FIXTURES . 'functions.php',
		];
	}

	private function function_message( string $name ): string {
		return $name . '() reports an error where it happens. Throw a dedicated exception from the exception '
			. 'namespace instead; the code that hands control back to WordPress decides what the user sees.';
	}

	private function transport_message( string $problem ): string {
		return sprintf( $problem, self::FIXTURE_NAMESPACE )
			. ' Throw a dedicated exception instead, and turn a WP_Error that WordPress returns into one with'
			. ' is_wp_error() where the call is made. Only the classes in'
			. ' parameters.architecture.noWordPressErrorMechanisms.boundaryAdapters may produce a WP_Error.';
	}
}
