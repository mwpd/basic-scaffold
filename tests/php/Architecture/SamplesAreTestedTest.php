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

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every sample class and production template has a unit or integration test.
 */
final class SamplesAreTestedTest extends TestCase {

	private TemporaryRepository $repository;

	protected function set_up() {
		parent::set_up();
		$this->repository = new TemporaryRepository();
		$this->repository->write(
			'phpstan.neon.dist',
			"parameters:\n    architecture:\n        namespace: NAMESPACE\n"
			. "        excludedNamespaces: [NAMESPACE\\Tests]\n"
			. "        dependencyDirection:\n            infrastructure: NAMESPACE\\Engine\n"
			. "            compositionRoot: [NAMESPACE\\Wiring\\Plugin]\n"
			. "        exceptions:\n            namespace: NAMESPACE\\Failures\n"
		);
	}

	protected function tear_down() {
		$this->repository->delete();
		parent::tear_down();
	}

	public function test_the_plugin_tests_every_sample_class_and_view(): void {
		$problems = ( new RepositoryChecks( RepositoryLayout::of_plugin() ) )->check_samples_are_tested();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_an_untested_subsystem_class_with_the_fix(): void {
		$this->write_class( 'Feature/Helper', 'final class Helper' );

		$this->assertSame( [ $this->class_message( 'Feature/Helper' ) ], $this->check() );
	}

	public function test_it_reports_an_untested_view_with_the_fix(): void {
		$this->repository->write( 'views/notice.php', '<p>Notice</p>' );

		$this->assertSame( [ $this->view_message( 'views/notice' ) ], $this->check() );
	}

	public function test_class_constants_and_covers_annotations_count_in_both_suites(): void {
		foreach ( [ 'Alias', 'Grouped', 'Qualified', 'Covered', 'MethodCovered' ] as $name ) {
			$this->write_class( 'Feature/' . $name, 'final class ' . $name );
		}
		$this->repository->write(
			'phpunit.xml.dist',
			'<phpunit><testsuites><testsuite name="unit">'
			. '<directory suffix="Test.php">tests/php/Unit</directory></testsuite>'
			. '<testsuite name="integration"><directory suffix="Test.php">tests/php/Integration</directory>'
			. '</testsuite></testsuites></phpunit>'
		);
		$this->repository->write(
			'tests/php/Unit/FeatureTest.php',
			'<?php namespace NAMESPACE\\Tests\\Unit; use NAMESPACE\\Feature\\Alias as Subject; '
			. 'use NAMESPACE\\Feature\\{Grouped as Other}; '
			. 'final class FeatureTest { public function test_it(): void { '
			. '$classes = [Subject::class, Other::class, \\NAMESPACE\\Feature\\Qualified::class]; }}'
		);
		$this->repository->write(
			'tests/php/Integration/FeatureTest.php',
			"<?php\n/** @covers \\NAMESPACE\\Feature\\Covered */\n"
			. "final class FeatureTest {\n/** @covers NAMESPACE\\Feature\\MethodCovered::run */\n"
			. 'public function test_it(): void {} }'
		);

		$this->assertSame( [], $this->check() );
	}

	public function test_only_concrete_feature_classes_are_checked(): void {
		$this->write_class( 'Engine/Internal', 'final class Internal' );
		$this->write_class( 'Failures/Internal', 'final class Internal' );
		$this->write_class( 'Tests/Fake', 'final class Fake' );
		$this->write_class( 'Wiring/Plugin', 'final class Plugin' );
		$this->write_class( 'Plugin', 'final class Plugin' );
		$this->write_class( 'Feature/BaseClass', 'abstract class BaseClass' );
		$this->write_class( 'Feature/Contract', 'interface Contract' );
		$this->write_class( 'Feature/HelperTrait', 'trait HelperTrait' );
		$this->write_class( 'EngineExtra/Helper', 'final class Helper' );

		$this->assertSame( [ $this->class_message( 'EngineExtra/Helper' ) ], $this->check() );
	}

	public function test_abstract_and_concrete_declarations_on_the_same_line_are_distinguished(): void {
		$this->repository->write(
			'src/Feature/Helper.php',
			'<?php namespace NAMESPACE\\Feature; abstract class BaseClass {} final class Helper {}'
		);

		$this->assertSame( [ $this->class_message( 'Feature/Helper' ) ], $this->check() );
	}

	public function test_references_outside_discovered_unit_and_integration_files_do_not_count(): void {
		$this->write_class( 'Feature/Helper', 'final class Helper' );
		$this->repository->write( 'views/notice.php', '<p>Notice</p>' );
		$this->repository->write(
			'phpunit.xml.dist',
			'<phpunit><testsuites><testsuite name="unit">'
			. '<directory suffix="Test.php">tests/php/Unit</directory>'
			. '<exclude>tests/php/Unit/Skipped</exclude></testsuite>'
			. '<testsuite name="architecture"><directory suffix="Test.php">tests/php/Architecture</directory>'
			. '</testsuite></testsuites></phpunit>'
		);
		foreach ( [
			'Unit/Skipped/HelperTest.php',
			'Unit/Helper.php',
			'Fixture/HelperTest.php',
			'Architecture/HelperTest.php',
			'Integration/HelperTest.php',
		] as $path ) {
			$this->repository->write(
				'tests/php/' . $path,
				"<?php \\NAMESPACE\\Feature\\Helper::class; // views/notice\n"
			);
		}

		$this->assertSame(
			[ $this->class_message( 'Feature/Helper' ), $this->view_message( 'views/notice' ) ],
			$this->check()
		);
	}

	public function test_mentions_imports_and_other_class_constants_do_not_count(): void {
		$this->write_class( 'Feature/Helper', 'final class Helper' );
		$this->repository->write(
			'tests/php/Unit/HelperTest.php',
			'<?php use NAMESPACE\\Feature\\Helper; '
			. '$name = "NAMESPACE\\Feature\\Helper::class"; Helper::OTHER; '
			. "// Helper::class @covers \\NAMESPACE\\Feature\\Helper\n"
			. '/** @covers \\NAMESPACE\\Feature\\HelperExtra */'
		);

		$this->assertSame( [ $this->class_message( 'Feature/Helper' ) ], $this->check() );
	}

	public function test_configured_suite_paths_suffixes_and_production_view_directories_are_used(): void {
		$this->repository->write(
			'phpunit.xml.dist',
			'<phpunit><testsuites><testsuite name="integration">'
			. '<directory suffix="Spec.php">specs</directory></testsuite></testsuites></phpunit>'
		);
		$this->repository->write( 'templates/nested/notice.php', '<p>Notice</p>' );
		$this->repository->write( 'test-views/fixture.php', '<p>Fixture</p>' );
		$this->repository->write( 'specs/NoticeSpec.php', "<?php // templates/nested/notice\n" );
		$layout = new RepositoryLayout(
			$this->repository->get_root(),
			'plugin.php',
			[ 'src' ],
			[ 'templates' ],
			'tests',
			[ 'test-views' ],
			[]
		);

		$this->assertSame( [], ( new RepositoryChecks( $layout ) )->check_samples_are_tested() );
		$this->repository->write( 'specs/NoticeSpec.php', "<?php // templates/nested/notice-extra\n" );
		$this->assertSame(
			[ $this->view_message( 'templates/nested/notice' ) ],
			( new RepositoryChecks( $layout ) )->check_samples_are_tested()
		);
	}

	/** @return array<string> */
	private function check(): array {
		return $this->repository->checks()->check_samples_are_tested();
	}

	private function write_class( string $path, string $declaration ): void {
		$namespace = dirname( $path );
		$this->repository->write(
			'src/' . $path . '.php',
			'<?php declare( strict_types=1 ); namespace NAMESPACE'
			. ( $namespace === '.' ? '' : '\\' . str_replace( '/', '\\', $namespace ) ) . '; '
			. $declaration . ' {}'
		);
	}

	private function class_message( string $path ): string {
		return '[architecture.samplesAreTested] Class ' . $this->repository->get_namespace() . '\\'
			. str_replace( '/', '\\', $path ) . ' in src/' . $path . '.php has no unit or integration test.'
			. ' Add a test in the unit or integration suite that references it with ::class or @covers. '
			. $this->explanation();
	}

	private function view_message( string $path ): string {
		return '[architecture.samplesAreTested] View ' . $path . '.php has no unit or integration test.'
			. ' Add a test in the unit or integration suite that names ' . $path
			. ' and exercises its output. ' . $this->explanation();
	}

	private function explanation(): string {
		return 'Principle 14: The code teaches, see docs/design-principles.md#14-the-code-teaches. '
			. 'Rule: docs/architecture-rules.md#architecturesamplesaretested.';
	}
}
