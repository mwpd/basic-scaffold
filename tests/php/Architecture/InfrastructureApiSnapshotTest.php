<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Composer\Autoload\ClassLoader;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Changes to the infrastructure's callable and extensible surface need review.
 */
final class InfrastructureApiSnapshotTest extends TestCase {

	private TemporaryRepository $repository;

	private ClassLoader $autoload;

	protected function set_up() {
		parent::set_up();
		$this->repository = new TemporaryRepository();
		$this->autoload   = new ClassLoader();
		$this->autoload->addPsr4( $this->repository->get_namespace() . '\\', $this->repository->get_root() . '/src' );
		$this->autoload->register();
		$this->repository->write(
			'phpstan.neon.dist',
			"parameters:\n    architecture:\n        namespace: NAMESPACE\n"
			. "        dependencyDirection:\n            infrastructure: NAMESPACE\\Engine\n"
		);
	}

	protected function tear_down() {
		$this->autoload->unregister();
		$this->repository->delete();
		parent::tear_down();
	}

	public function test_the_infrastructure_api_matches_the_snapshot(): void {
		$problems = ( new InfrastructureApiSnapshot( RepositoryLayout::of_plugin() ) )->check();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_final_classes_contribute_only_public_methods(): void {
		$this->write_type(
			'Closed',
			'final class Closed { public const VISIBLE = 1; protected const HIDDEN = 2; '
			. 'public $visible = true; protected $hidden; private $secret; '
			. 'public function run(): void {} protected function extend(): void {} private function hide(): void {} }'
		);

		$this->assertSame(
			"Engine\\Closed final class\nEngine\\Closed::public function run(): void\n",
			$this->builder()->build()
		);
	}

	public function test_extensible_types_contribute_public_and_protected_members(): void {
		foreach ( [
			'Open'     => 'class',
			'BaseType' => 'abstract class',
			'Reusable' => 'trait',
		] as $name => $kind ) {
			$this->write_type(
				$name,
				$kind . ' ' . $name . ' { '
				. ( $kind === 'trait' ? ''
					: 'public const VALUE = 1; protected const FLAG = false; private const SECRET = 2; ' )
				. 'public string $name = "sample"; protected ?string $optional = null; protected int $uninitialized; '
				. 'private $hidden; public static $shared = []; '
				. 'final public function run(): void {} protected static function extend(): self {} '
				. 'private function hide(): void {} }'
			);
		}
		$this->write_type( 'Contract', 'interface Contract { public const VALUE = 1; public function run(): void; }' );

		$expected = [
			'Engine\\Contract interface',
			'Engine\\Contract::public abstract function run(): void',
			'Engine\\Contract::public const VALUE = 1',
		];
		foreach ( [
			'Open'     => 'class',
			'BaseType' => 'abstract class',
			'Reusable' => 'trait',
		] as $name => $kind ) {
			$prefix     = 'Engine\\' . $name;
			$expected[] = $prefix . ' ' . $kind;
			if ( $kind !== 'trait' ) {
				$expected[] = $prefix . '::public const VALUE = 1';
				$expected[] = $prefix . '::protected const FLAG = false';
			}
			$expected[] = $prefix . '::public string $name = "sample"';
			$expected[] = $prefix . '::protected ?string $optional = null';
			$expected[] = $prefix . '::protected int $uninitialized';
			$expected[] = $prefix . '::public static $shared = []';
			$expected[] = $prefix . '::public final function run(): void';
			$expected[] = $prefix . '::protected static function extend(): self';
		}
		sort( $expected, SORT_STRING );

		$this->assertSame( implode( "\n", $expected ) . "\n", $this->builder()->build() );
	}

	public function test_signatures_and_defaults_are_normalized_without_paths_or_root_namespaces(): void {
		$this->write_type(
			'Contract',
			'abstract class Contract { public const NAME = "NAMESPACE\\Engine\\Contract"; '
			. 'protected const DATA = ["line" => "a\nb", "flags" => [true, false, null], 2 => 1.0]; '
			. 'public const ROOT = __DIR__; '
			. 'abstract protected function &resolve(?\DateTimeImmutable &$date, '
			. '?\NAMESPACE\Engine\Contract $other = null, array $data = self::DATA, '
			. 'string $name = self::NAME, callable ...$callbacks): ?self; '
			. 'public function __toString(): string { return ""; } }'
		);

		$this->assertSame(
			"Engine\\Contract abstract class\n"
			. 'Engine\\Contract::protected abstract function &resolve( ?\DateTimeImmutable &$date, '
			. '?Engine\\Contract $other = null, array $data = ["line" => "a\nb", '
			. '"flags" => [true, false, null], 2 => 1.0], string $name = "Engine\\\\Contract", '
			. 'callable ...$callbacks ): ?self' . "\n"
			. 'Engine\\Contract::protected const DATA = ["line" => "a\nb", '
			. '"flags" => [true, false, null], 2 => 1.0]' . "\n"
			. 'Engine\\Contract::public const NAME = "Engine\\\\Contract"' . "\n"
			. 'Engine\\Contract::public const ROOT = "<root>/src/Engine"' . "\n"
			. "Engine\\Contract::public function __toString(): string\n",
			$this->builder()->build()
		);
	}

	public function test_discovery_is_sorted_scoped_and_includes_inherited_members(): void {
		$this->write_type(
			'Zebra',
			'class Zebra { public function z( $untyped = null ) {} protected function a(): void {} }'
		);
		$this->write_type( 'Alpha', 'final class Alpha extends Zebra {}' );
		$this->repository->write(
			'src/EngineExtra/Outside.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE\\EngineExtra; final class Outside {}'
		);

		$this->assertSame(
			"Engine\\Alpha final class\nEngine\\Alpha::public function z( \$untyped = null )\n"
			. "Engine\\Zebra class\nEngine\\Zebra::protected function a(): void\n"
			. "Engine\\Zebra::public function z( \$untyped = null )\n",
			$this->builder()->build()
		);
	}

	public function test_php_owned_members_do_not_pin_the_snapshot_to_a_php_version(): void {
		$this->write_type( 'Collection', 'final class Collection extends \\ArrayObject {}' );

		$this->assertSame( "Engine\\Collection final class\n", $this->builder()->build() );
	}

	public function test_it_reports_a_difference_with_the_diff_and_regeneration_command(): void {
		$this->write_type( 'Contract', 'interface Contract { public function run(): void; }' );
		$this->repository->write(
			InfrastructureApiSnapshot::PATH,
			"Engine\\Contract interface\nEngine\\Contract::public abstract function run(): string\n"
		);
		$problems = $this->builder()->check();

		$this->assertCount( 1, $problems );
		$this->assertStringContainsString( '[architecture.infrastructureApiSnapshot]', $problems[0] );
		$this->assertStringContainsString( InfrastructureApiSnapshot::PATH, $problems[0] );
		$this->assertStringContainsString( 'composer api:snapshot', $problems[0] );
		$this->assertStringContainsString( '-Engine\\Contract::public abstract function run(): string', $problems[0] );
		$this->assertStringContainsString( '+Engine\\Contract::public abstract function run(): void', $problems[0] );
		$this->assertStringContainsString(
			RuleManifest::get_explanation( RuleManifest::INFRASTRUCTURE_API_SNAPSHOT ),
			$problems[0]
		);
		$this->repository->write( InfrastructureApiSnapshot::PATH, $this->builder()->build() );
		$this->assertSame( [], $this->builder()->check() );
	}

	public function test_a_missing_snapshot_reports_the_fix(): void {
		$this->write_type( 'Marker', 'interface Marker {}' );
		$problems = $this->builder()->check();

		$this->assertCount( 1, $problems );
		$this->assertStringContainsString( 'composer api:snapshot', $problems[0] );
		$this->assertStringContainsString( '+Engine\\Marker interface', $problems[0] );
	}

	private function builder(): InfrastructureApiSnapshot {
		return new InfrastructureApiSnapshot( $this->repository->get_layout() );
	}

	private function write_type( string $name, string $declaration ): void {
		$this->repository->write(
			'src/Engine/' . $name . '.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE\\Engine; ' . $declaration
		);
	}
}
