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

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey;
use Composer\Autoload\ClassLoader;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every subsystem service is reachable from the composition root.
 */
final class ServicesDeclaredTest extends TestCase {

	private TemporaryRepository $repository;

	private ClassLoader $autoload;

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		$this->repository = new TemporaryRepository();
		$this->autoload   = new ClassLoader();
		$this->autoload->addPsr4(
			$this->repository->get_namespace() . '\\',
			$this->repository->get_root() . '/src'
		);
		$this->autoload->register();
		$this->repository->write(
			'phpstan.neon.dist',
			"parameters:\n    architecture:\n        namespace: NAMESPACE\n"
			. "        excludedNamespaces: [NAMESPACE\\Tests]\n"
			. "        dependencyDirection:\n            infrastructure: NAMESPACE\\Engine\n"
			. "        exceptions:\n            namespace: NAMESPACE\\Failures\n"
		);
		$this->repository->write(
			'src/Plugin.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE; '
			. 'final class Plugin extends \\MWPD\\BasicScaffold\\Infrastructure\\ServiceBasedPlugin {'
			. 'protected function get_service_classes(): array { return ['
			. '"direct" => Feature\\Declared::class,'
			. '"callable" => static function (): string { return Feature\\FromCallback::class; }'
			. ']; }}'
		);
	}

	protected function tear_down() {
		$this->autoload->unregister();
		$this->repository->delete();
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_the_plugin_declares_every_subsystem_service(): void {
		$problems = ( new CompositionRootContract( RepositoryLayout::COMPOSITION_ROOT ) )
			->check_services_declared( RepositoryLayout::of_plugin() );

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_an_undeclared_subsystem_service_with_the_fix(): void {
		$this->write_service( 'Feature/Undeclared', 'final class Undeclared' );

		$this->assertSame(
			[ $this->undeclared_message( 'Feature\\Undeclared' ) ],
			$this->check()
		);
	}

	public function test_declared_callable_and_factory_built_services_pass(): void {
		$this->write_service( 'Feature/Declared', 'final class Declared' );
		$this->write_service( 'Feature/FromCallback', 'final class FromCallback' );
		$this->write_service( 'Feature/BuiltByFactory', 'final class BuiltByFactory' );

		$this->assertSame(
			[],
			$this->check(
				[
					$this->repository->get_namespace() . '\Feature\BuiltByFactory' => 'FeatureFactory::create()',
				]
			)
		);
	}

	public function test_only_concrete_feature_services_are_checked(): void {
		$this->write_service( 'Engine/InternalService', 'final class InternalService' );
		$this->write_service( 'Failures/InternalService', 'final class InternalService' );
		$this->write_service( 'Tests/FakeService', 'final class FakeService' );
		$this->write_service( 'Feature/AbstractService', 'abstract class AbstractService' );
		$this->write_service( 'Feature/Contract', 'interface Contract extends', false );
		$this->write_service( 'RootService', 'final class RootService' );
		$this->repository->write(
			'src/Feature/Helper.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE\\Feature; final class Helper {}'
		);
		$this->repository->write(
			'src/Feature/HelperTrait.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE\\Feature; trait HelperTrait {}'
		);
		$this->write_service( 'EngineExtra/Undeclared', 'final class Undeclared' );

		$this->assertSame( [ $this->undeclared_message( 'EngineExtra\\Undeclared' ) ], $this->check() );
	}

	public function test_concrete_services_inheriting_the_marker_are_checked(): void {
		$this->write_service( 'Feature/BaseService', 'abstract class BaseService' );
		$this->repository->write(
			'src/Feature/ChildService.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE\\Feature; '
			. 'final class ChildService extends BaseService {}'
		);

		$this->assertSame( [ $this->undeclared_message( 'Feature\\ChildService' ) ], $this->check() );
	}

	/**
	 * @param array<string, string> $built_elsewhere Class names and the routes that build them.
	 * @return array<string>
	 */
	private function check( array $built_elsewhere = [] ): array {
		$plugin = $this->repository->get_namespace() . '\Plugin';
		assert( is_a( $plugin, ServiceBasedPlugin::class, true ) );

		return ( new CompositionRootContract( $plugin ) )
			->check_services_declared( $this->repository->get_layout( $built_elsewhere ) );
	}

	private function write_service( string $path, string $declaration, bool $is_class = true ): void {
		$namespace = dirname( $path );
		$namespace = $namespace === '.' ? '' : '\\' . str_replace( '/', '\\', $namespace );
		$this->repository->write(
			'src/' . $path . '.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE' . $namespace . '; '
			. $declaration . ( $is_class ? ' implements' : '' )
			. ' \\MWPD\\BasicScaffold\\Infrastructure\\Service {}'
		);
	}

	private function undeclared_message( string $class_name ): string {
		return '[architecture.servicesDeclared] Service ' . $this->repository->get_namespace() . '\\' . $class_name
			. ' is not declared in ' . $this->repository->get_namespace() . '\Plugin.'
			. ' Declare it in get_service_classes(),'
			. ' or list it in RepositoryLayout::SERVICES_BUILT_ELSEWHERE with the route that builds it. '
			. 'Principle 5: One composition root builds the object graph, see '
			. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
			. 'Pattern: docs/patterns.md#i-need-to-add-a-feature. '
			. 'Rule: docs/architecture-rules.md#architectureservicesdeclared.';
	}
}
