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
 * @extends RuleTestCase<NoServiceLocatorRule>
 */
final class NoServiceLocatorRuleTest extends RuleTestCase {

	private const FIXTURES  = __DIR__ . '/../Fixture/NoServiceLocator/';
	private const ROOT      = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator';
	private const CONTAINER = 'MWPD\BasicScaffold\Infrastructure\ServiceContainer';
	private const TIP       = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Pattern: docs/patterns.md#i-need-a-service-locator. '
		. 'Rule: docs/architecture-rules.md#architecturenoservicelocator';

	private string $container = self::CONTAINER;

	protected function getRule(): Rule {
		return new NoServiceLocatorRule(
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
			$this->container
		);
	}

	/** @dataProvider container_positions */
	public function test_it_rejects_the_container_in_every_dependency_position( string $owner, int $line ): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/' . $owner . '.php' ],
			[ [ $this->message( 'Feature\\' . $owner ), $line, self::TIP ] ]
		);
	}

	/** @return array<string, array{string, int}> */
	public function container_positions(): array {
		return [
			'constructor parameter'   => [ 'Constructor', 8 ],
			'group import'            => [ 'Imported', 7 ],
			'property type'           => [ 'Property', 8 ],
			'return type'             => [ 'Returned', 8 ],
			'code reference'          => [ 'Code', 9 ],
			'docblock property'       => [ 'Documented', 7 ],
			'docblock parameter'      => [ 'DocumentedMethod', 8 ],
			'aliased docblock method' => [ 'Aliased', 9 ],
			'template bound'          => [ 'Templated', 7 ],
			'assertion tag'           => [ 'Asserted', 8 ],
			'interface inheritance'   => [ 'Inherited', 7 ],
			'loose code'              => [ 'Loose', 7 ],
			'docblock return'         => [ 'DocumentedReturn', 8 ],
			'docblock var'            => [ 'DocumentedVar', 8 ],
			'docblock throws'         => [ 'DocumentedThrows', 8 ],
			'first repeated mention'  => [ 'Repeated', 7 ],
		];
	}

	public function test_it_checks_anonymous_classes(): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/Anonymous.php' ],
			[ [ $this->message( 'Feature\class@anonymous' ), 10, self::TIP ] ]
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

	public function test_it_respects_the_configured_container(): void {
		$this->container = '\\STDCLASS';
		$this->analyse(
			[ self::FIXTURES . 'Feature/Custom.php' ],
			[ [ $this->message( 'Feature\Custom', 'stdClass' ), 8, self::TIP ] ]
		);
	}

	private function message( string $owner, string $container = self::CONTAINER ): string {
		return self::ROOT . '\\' . $owner . ' depends on the service container ' . $container . '.'
			. ' Receive the collaborator through the constructor instead of looking it up in the container.';
	}
}
