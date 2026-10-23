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
 * The three rules about where hooks are added, each tested against the same
 * fixtures.
 *
 * @phpstan-type TestedRule (
 *     NoHooksInConstructorRule
 *     | HooksInLifecycleMethodsRule
 *     | NoContextChecksInRegisterRule
 * )
 * @extends RuleTestCase<TestedRule>
 */
final class HookPlacementRulesTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Hooks/';

	private const HOOKS = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks';

	private const BAD = self::HOOKS . '\Feature\BadService';

	/**
	 * Rule under test.
	 *
	 * @var TestedRule
	 */
	private Rule $rule;

	protected function getRule(): Rule {
		return $this->rule;
	}

	public function test_constructors_do_not_hook_into_wordpress(): void {
		$this->rule = new NoHooksInConstructorRule(
			new PluginNamespace( self::HOOKS ),
			$this->createReflectionProvider()
		);

		$tip = $this->tip( 'nohooksinconstructor', 'i-need-to-add-hooks' );

		$this->analyse(
			$this->fixtures(),
			[
				[
					$this->constructor( 'add_action', self::HOOKS . '\Infrastructure\Orchestrator::__construct()' ),
					10,
					$tip,
				],
				[ $this->constructor( 'add_filter', self::BAD . '::__construct()' ), 12, $tip ],
			]
		);
	}

	public function test_feature_services_change_hooks_only_in_lifecycle_methods(): void {
		$this->rule = new HooksInLifecycleMethodsRule(
			new PluginNamespace( self::HOOKS ),
			self::HOOKS . '\Infrastructure',
			self::HOOKS . '\Exception',
			[ self::HOOKS . '\Plugin' ],
			[
				'register'   => self::HOOKS . '\Infrastructure\Registerable',
				'activate'   => self::HOOKS . '\Infrastructure\Activateable',
				'deactivate' => self::HOOKS . '\Infrastructure\Deactivateable',
			],
			$this->createReflectionProvider()
		);

		$tip = $this->tip( 'hooksinlifecyclemethods', 'i-need-to-add-hooks' );

		$this->analyse(
			$this->fixtures(),
			[
				[ $this->lifecycle( 'add_action', 'a closure in ' . self::BAD . '::__construct()' ), 14, $tip ],
				[ $this->lifecycle( 'add_filter', 'a closure in ' . self::BAD . '::register()' ), 26, $tip ],
				[ $this->lifecycle( 'add_action', self::BAD . '::helper()' ), 33, $tip ],
				[ $this->lifecycle( 'remove_action', self::BAD . '::filter()' ), 37, $tip ],
				[ $this->lifecycle( 'add_action', 'a closure in ' . self::BAD . '::arrow()' ), 41, $tip ],
				[ $this->lifecycle( 'add_action', self::HOOKS . '\Feature\NotRegisterable::register()' ), 10, $tip ],
			]
		);
	}

	public function test_register_leaves_context_checks_to_conditional(): void {
		$this->rule = new NoContextChecksInRegisterRule(
			new PluginNamespace( self::HOOKS ),
			[ 'is_admin', 'is_singular' ],
			$this->createReflectionProvider(),
			[]
		);

		$this->analyse(
			$this->fixtures(),
			[
				[
					'is_admin() in ' . self::BAD . '::register() decides at registration whether the service applies'
						. ' to this request. Implement Conditional and return the answer from is_needed(), so the'
						. ' plugin does not even build the service when it is not needed.',
					19,
					$this->tip( 'nocontextchecksinregister', 'i-need-a-service-only-in-some-requests' ),
				],
			]
		);
	}

	public function test_register_leaves_action_timing_to_delayed(): void {
		$functions = [
			'did_action',
			'doing_action',
			'did_filter',
			'doing_filter',
			'current_action',
			'current_filter',
		];

		$this->rule = new NoContextChecksInRegisterRule(
			new PluginNamespace( self::HOOKS ),
			[],
			$this->createReflectionProvider(),
			$functions
		);

		$errors = [];
		foreach ( $functions as $offset => $function ) {
			$errors[] = [
				$function . '() in ' . self::HOOKS . '\Feature\TimingService::register() waits for an action itself.'
					. ' Implement Delayed and return the action from get_registration_action(), so the plugin defers'
					. ' the service until that action.',
				12 + $offset,
				$this->tip( 'nocontextchecksinregister', 'i-need-a-service-only-in-some-requests' ),
			];
		}

		$this->analyse( $this->fixtures(), $errors );
	}

	/**
	 * @return array<string>
	 */
	private function fixtures(): array {
		$files = [
			'Infrastructure/Registerable',
			'Infrastructure/Activateable',
			'Infrastructure/Deactivateable',
			'Infrastructure/Orchestrator',
			'Feature/GoodService',
			'Feature/BadService',
			'Feature/NotRegisterable',
			'Feature/TimingService',
			'Plugin',
		];

		return array_map( static fn( string $file ): string => self::FIXTURES . $file . '.php', $files );
	}

	private function constructor( string $called, string $location ): string {
		return "{$called}() in {$location} hooks into WordPress while the object is being built. Constructors"
			. ' only store dependencies; add hooks in register() (Registerable), which the plugin calls at the right'
			. ' time.';
	}

	private function lifecycle( string $called, string $location ): string {
		return "{$called}() in {$location} changes a hook outside a lifecycle method. Add it in register() of a"
			. ' class implementing Registerable, or in activate() or deactivate() for Activateable and'
			. ' Deactivateable; if it must wait for a later action, implement Delayed.';
	}

	private function tip( string $anchor, string $pattern ): string {
		return 'Principle 7: Show the alternative to WordPress anti-patterns, see'
			. ' docs/design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns.'
			. ' Pattern: docs/patterns.md#' . $pattern . '.'
			. ' Rule: docs/architecture-rules.md#architecture' . $anchor;
	}
}
