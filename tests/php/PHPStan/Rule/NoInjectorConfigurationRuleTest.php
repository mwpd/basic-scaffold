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
use PHPStan\Type\FileTypeMapper;

/**
 * @extends RuleTestCase<NoInjectorConfigurationRule>
 */
final class NoInjectorConfigurationRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/NoServiceLocator/';
	private const ROOT     = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator';
	private const INJECTOR = 'MWPD\BasicScaffold\Infrastructure\Injector';
	private const TIP      = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Pattern: docs/patterns.md#i-need-a-service-locator. '
		. 'Rule: docs/architecture-rules.md#architecturenoservicelocator';

	/** @var array<string> */
	private array $allowed   = [ 'make' ];
	private string $injector = self::INJECTOR;

	protected function getRule(): Rule {
		return new NoInjectorConfigurationRule(
			new DependencyContext(
				self::ROOT,
				[ self::ROOT . '\TestDouble' ],
				self::ROOT . '\Exception',
				self::ROOT . '\Infrastructure',
				[ self::ROOT . '\Plugin' ],
				[ (string) realpath( self::FIXTURES . 'views' ) ],
				$this->createReflectionProvider(),
				self::getContainer()->getByType( FileTypeMapper::class )
			),
			$this->injector,
			$this->allowed
		);
	}

	public function test_it_reports_every_injector_configuration_method_with_guidance(): void {
		$errors = [];
		foreach ( [ 'bind', 'share', 'delegate', 'bind_argument' ] as $offset => $method ) {
			$errors[] = [ $this->message( $method ), 9 + $offset, self::TIP ];
		}
		$this->analyse( [ self::FIXTURES . 'Feature/Reconfigures.php' ], $errors );
	}

	public function test_it_resolves_a_known_dynamic_method_name(): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/Dynamic.php' ],
			[ [ $this->message( 'bind' ), 10, self::TIP ] ]
		);
	}

	public function test_it_allows_on_demand_creation_and_unrelated_methods(): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/Creates.php', self::FIXTURES . 'Feature/Unrelated.php' ],
			[]
		);
	}

	public function test_it_only_checks_feature_subsystems(): void {
		$this->analyse(
			array_map(
				static fn( string $file ): string => self::FIXTURES . $file . '.php',
				[
					'Infrastructure/Wiring',
					'Plugin',
					'TopLevel',
					'Exception/Failure',
					'TestDouble/Fake',
					'outside',
					'views/notice',
				]
			),
			[]
		);
	}

	public function test_it_respects_the_allowed_methods_configuration_case_insensitively(): void {
		$this->allowed = [ 'MAKE', 'BIND', 'SHARE', 'DELEGATE', 'BIND_ARGUMENT' ];
		$this->analyse( [ self::FIXTURES . 'Feature/Reconfigures.php', self::FIXTURES . 'Feature/Creates.php' ], [] );
	}

	public function test_it_can_remove_make_from_the_allowlist(): void {
		$this->allowed = [];
		$this->analyse(
			[ self::FIXTURES . 'Feature/Creates.php' ],
			[ [ $this->message( 'MaKe' ), 13, self::TIP ] ]
		);
	}

	public function test_it_restricts_methods_on_the_configured_injector(): void {
		$this->injector = 'ArrayObject';
		$this->analyse(
			[ self::FIXTURES . 'Feature/Custom.php' ],
			[ [ str_replace( self::INJECTOR, 'ArrayObject', $this->message( 'append' ) ), 9, self::TIP ] ]
		);
	}

	public function test_it_checks_nullable_union_and_inherited_injector_types_and_unknown_methods(): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/TypedCalls.php', self::FIXTURES . 'Feature/AnonymousCall.php' ],
			[
				[ $this->message( 'bind' ), 9, self::TIP ],
				[ $this->message( 'share' ), 13, self::TIP ],
				[ $this->message( 'bind_argument' ), 16, self::TIP ],
				[ $this->message( '{dynamic}' ), 19, self::TIP ],
				[ $this->message( 'share' ), 11, self::TIP ],
			]
		);
	}

	public function test_it_respects_the_configured_injector_case_insensitively(): void {
		$this->injector = '\\ARRAYOBJECT';
		$this->allowed  = [ 'append' ];
		$this->analyse( [ self::FIXTURES . 'Feature/Custom.php' ], [] );
	}

	private function message( string $method ): string {
		return 'Calls ' . self::INJECTOR . '::' . $method . '() in a feature subsystem.'
			. ' Configure bindings, sharing and delegations in the composition root;'
			. ' receive collaborators through the constructor or use the allowed injector methods'
			. ' for on-demand creation.';
	}
}
