<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Infrastructure\Autoloader;
use MWPD\BasicScaffold\Tests\Architecture\TemporaryRepository;

final class AutoloaderTest extends TestCase {

	private TemporaryRepository $repository;

	private Autoloader $autoloader;

	protected function set_up() {
		parent::set_up();
		// Reuse the filesystem fixture builder; each instance has its own namespace.
		$this->repository = new TemporaryRepository();
		$this->autoloader = new Autoloader();
	}

	protected function tear_down() {
		$this->autoloader->unregister();
		$this->repository->delete();
		parent::tear_down();
	}

	/**
	 * @dataProvider namespace_mappings
	 */
	public function test_it_maps_a_normalized_namespace_to_nested_directories(
		string $leading,
		string $trailing
	): void {
		$class = $this->write_class( 'mapped/Nested/Example.php', 'Nested', 'Example' );
		$this->autoloader->add_namespace(
			$leading . $this->repository->get_namespace() . $trailing,
			$this->repository->get_root() . '/mapped' . ( '' === $trailing ? '' : '/' )
		);

		$this->assertFalse( class_exists( $class, false ) );
		$this->autoloader->autoload( $class );
		$this->assertTrue( class_exists( $class, false ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function namespace_mappings(): array {
		return [
			'no separators'      => [ '', '' ],
			'leading separator'  => [ '\\', '' ],
			'trailing separator' => [ '', '\\' ],
			'both separators'    => [ '\\', '\\' ],
		];
	}

	public function test_it_adds_a_filename_prefix(): void {
		$class = $this->write_class( 'class-Example.php', '', 'Example' );
		$this->autoloader->add_namespace( $this->repository->get_namespace(), $this->repository->get_root(), 'class-' );

		$this->autoloader->autoload( $class );

		$this->assertTrue( class_exists( $class, false ) );
	}

	public function test_it_uses_a_custom_filename_suffix(): void {
		$class = $this->write_class( 'Example.inc', '', 'Example' );
		$this->autoloader->add_namespace(
			$this->repository->get_namespace(),
			$this->repository->get_root(),
			'',
			'.inc'
		);

		$this->autoloader->autoload( $class );

		$this->assertTrue( class_exists( $class, false ) );
	}

	public function test_it_lowercases_the_relative_path(): void {
		$class = $this->write_class( 'nested/example.php', 'Nested', 'Example' );
		$this->autoloader->add_namespace(
			$this->repository->get_namespace(),
			$this->repository->get_root(),
			'',
			'.php',
			true
		);

		$this->autoloader->autoload( $class );

		$this->assertTrue( class_exists( $class, false ) );
	}

	public function test_it_replaces_underscores_with_hyphens(): void {
		$class = $this->write_class( 'Nested_Path/Example_Name.php', 'Nested_Path', 'Example_Name' );
		$other = $this->write_class( 'Other-Path/Other-Name.php', 'Other_Path', 'Other_Name' );
		// Underscores are preserved by default, including directory names.
		$this->autoloader->add_namespace( $this->repository->get_namespace(), $this->repository->get_root() );
		$this->autoloader->autoload( $class );
		$this->assertTrue( class_exists( $class, false ) );

		$this->autoloader->add_namespace(
			$this->repository->get_namespace(),
			$this->repository->get_root(),
			'',
			'.php',
			false,
			true
		);
		$this->autoloader->autoload( $other );
		$this->assertTrue( class_exists( $other, false ) );
	}

	public function test_it_loads_from_multiple_namespace_mappings(): void {
		$first  = $this->write_class( 'first/Example.php', 'First', 'Example' );
		$second = $this->write_class( 'second/Example.php', 'Second', 'Example' );
		$this->autoloader
			->add_namespace(
				$this->repository->get_namespace() . '\\First',
				$this->repository->get_root() . '/first'
			)
			->add_namespace(
				$this->repository->get_namespace() . '\\Second',
				$this->repository->get_root() . '/second'
			);

		$this->autoloader->autoload( $first );
		$this->autoloader->autoload( $second );

		$this->assertTrue( class_exists( $first, false ) );
		$this->assertTrue( class_exists( $second, false ) );
	}

	public function test_it_silently_ignores_unknown_classes_and_unmapped_namespaces(): void {
		$this->write_class( 'Example.php', '', 'Example' );
		$this->autoloader->add_namespace( $this->repository->get_namespace(), $this->repository->get_root() );
		$before = get_included_files();

		$this->autoloader->autoload( $this->repository->get_namespace() . '\\Missing' );
		$this->autoloader->autoload( $this->repository->get_namespace() . 'Other\\Example' );

		$this->assertSame( $before, get_included_files() );
		$this->assertFalse( class_exists( $this->repository->get_namespace() . '\\Missing', false ) );
	}

	public function test_it_registers_with_and_unregisters_from_spl(): void {
		$class = $this->write_class( 'Example.php', '', 'Example' );
		$other = $this->write_class( 'Other.php', '', 'Other' );
		$this->autoloader->add_namespace( $this->repository->get_namespace(), $this->repository->get_root() );
		$before = spl_autoload_functions();

		$this->autoloader->register();
		$this->assertTrue( class_exists( $class ) );
		$this->autoloader->unregister();

		$this->assertSame( $before, spl_autoload_functions() );
		$this->assertFalse( class_exists( $other ) );
	}

	private function write_class( string $path, string $relative_namespace, string $name ): string {
		$relative_namespace = '' === $relative_namespace ? '' : '\\' . $relative_namespace;
		$this->repository->write(
			$path,
			"<?php\ndeclare( strict_types=1 );\nnamespace NAMESPACE{$relative_namespace};\nfinal class {$name} {}\n"
		);

		return $this->repository->get_namespace() . $relative_namespace . '\\' . $name;
	}
}
