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
 * The repository's structure follows the rules that look at files.
 *
 * Each rule is checked twice: on this plugin, where it has to pass, and on a
 * temporary repository that breaks it, to prove that the check notices.
 */
final class RepositoryStructureTest extends TestCase {

	/**
	 * Temporary repository of the current test, if any.
	 */
	private ?TemporaryRepository $repository = null;

	protected function tear_down() {
		if ( $this->repository !== null ) {
			$this->repository->delete();
			$this->repository = null;
		}

		parent::tear_down();
	}

	public function test_the_plugin_declares_strict_types(): void {
		$this->assertNoProblems( $this->plugin_checks()->check_strict_types() );
	}

	public function test_it_reports_a_class_file_without_strict_types(): void {
		$problems = $this->repository()
			->write( 'src/Service.php', $this->php( 'namespace NAMESPACE;', '', 'final class Service {', '}' ) )
			->write( 'views/notice.php', $this->php( 'function helper() {', '}' ) )
			->write(
				'views/conditional.php',
				$this->php( "if ( ! function_exists( 'helper' ) ) {", '	function helper() {', '	}', '}' )
			)
			->write( 'views/closure.php', $this->php( '$format = static function ( $value ) {', '};' ) )
			->write( 'views/fine.php', "<p><?= 'Fine.' ?></p>\n" )
			->checks()
			->check_strict_types();

		$this->assertProblems(
			[
				'[architecture.strictTypes] src/Service.php does not declare strict types.',
				'[architecture.strictTypes] views/conditional.php is a view template but declares a class,'
					. ' interface, trait or function.',
				'[architecture.strictTypes] views/notice.php is a view template but declares a class, interface,'
					. ' trait or function.',
			],
			$problems
		);
	}

	public function test_the_plugin_declares_one_class_per_file_named_after_its_path(): void {
		$this->assertNoProblems( $this->plugin_checks()->check_declarations_follow_psr4() );
	}

	public function test_it_reports_files_that_do_not_follow_psr4(): void {
		$namespace = $this->repository()->get_namespace();
		$problems  = $this->repository()
			->write(
				'src/Service.php',
				$this->php( 'declare( strict_types=1 );', '', 'namespace NAMESPACE;', 'final class Servise {', '}' )
			)
			->write(
				'src/Pair.php',
				$this->php( 'namespace NAMESPACE;', '', 'interface Pair {', '}', '', 'final class Other {', '}' )
			)
			->write(
				'src/Factory.php',
				$this->php(
					'namespace NAMESPACE;',
					'',
					'final class Factory {',
					'	public function make(): object {',
					'		return new class() {',
					'		};',
					'	}',
					'}'
				)
			)
			->write( 'src/lowercase.php', $this->php( 'namespace NAMESPACE;', 'final class Lowercase {', '}' ) )
			->write(
				'src/Switched.php',
				$this->php( 'namespace NAMESPACE {', '}', 'namespace Other {', 'final class Switched {', '}', '}' )
			)
			->write(
				'tests/php/Fixture/Anything.php',
				$this->php( 'final class One {', '}', 'final class Two {', '}' )
			)
			->checks()
			->check_declarations_follow_psr4();

		$this->assertProblems(
			[
				'[architecture.declarationsFollowPsr4] src/Pair.php declares 2 classes, interfaces or traits'
					. ' instead of one.',
				"[architecture.declarationsFollowPsr4] src/Service.php declares {$namespace}\\Servise, but its"
					. " path makes the autoloaders look for {$namespace}\\Service.",
				'[architecture.declarationsFollowPsr4] src/Switched.php declares Other\\Switched, but its path'
					. " makes the autoloaders look for {$namespace}\\Switched.",
				"[architecture.declarationsFollowPsr4] src/lowercase.php declares {$namespace}\\Lowercase, but"
					. " its path makes the autoloaders look for {$namespace}\\lowercase.",
			],
			$problems
		);
	}

	public function test_it_follows_psr4_roots_that_map_to_several_directories(): void {
		$namespace = $this->repository()->get_namespace();
		$problems  = $this->repository()
			->write(
				'composer.json',
				(string) json_encode( [ 'autoload' => [ 'psr-4' => [ $namespace . '\\' => [ 'src/', 'lib/' ] ] ] ] )
			)
			->write( 'lib/Helper.php', $this->php( 'namespace NAMESPACE;', 'final class Helpers {', '}' ) )
			->checks()
			->check_declarations_follow_psr4();

		$this->assertProblems(
			[ '[architecture.declarationsFollowPsr4] lib/Helper.php declares ' ],
			$problems
		);
	}

	public function test_the_plugin_has_no_side_effects_on_include(): void {
		$this->assertNoProblems( $this->plugin_checks()->check_no_side_effects_on_include() );
	}

	public function test_it_reports_code_that_runs_on_include(): void {
		$problems = $this->repository()
			->write(
				'src/Service.php',
				$this->php(
					'declare( strict_types=1 );',
					'',
					'namespace NAMESPACE;',
					'',
					'use Exception;',
					'',
					'final class Service {',
					'	public function register(): void {',
					"		add_action( 'init', 'x' );",
					'	}',
					'}',
					'',
					"add_action( 'init', [ new Service(), 'register' ] );",
					'',
					'function helper(): void {',
					'}'
				)
			)
			->write( 'src/Ticks.php', $this->php( 'declare( ticks=1 ) {', "	echo 'Tick.';", '}' ) )
			->write( 'src/Trailing.php', $this->php( 'final class Trailing {', '}', '?>', '', ' ' ) )
			->checks()
			->check_no_side_effects_on_include();

		$this->assertProblems(
			[
				'[architecture.noSideEffectsOnInclude] src/Service.php:15 runs code when the file is included'
					. ' (add_action',
				'[architecture.noSideEffectsOnInclude] src/Service.php:17 runs code when the file is included'
					. ' (function helper',
				'[architecture.noSideEffectsOnInclude] src/Ticks.php:3 runs code when the file is included'
					. ' (declare ( ticks = 1 )',
				'[architecture.noSideEffectsOnInclude] src/Trailing.php:6 runs code when the file is included'
					. ' (output outside the PHP tags)',
			],
			$problems
		);
	}

	public function test_the_plugin_requires_nothing_but_php_at_runtime(): void {
		$this->assertNoProblems( $this->plugin_checks()->check_runtime_requirements() );
	}

	public function test_it_reports_a_runtime_requirement(): void {
		$problems = $this->repository()
			->write( 'composer.json', '{"require": {"php": "^7.4", "acme/library": "^1.0"}}' )
			->checks()
			->check_runtime_requirements();

		$this->assertProblems(
			[ '[architecture.noRuntimeDependencies] composer.json requires acme/library at runtime.' ],
			$problems
		);
	}

	public function test_the_plugin_puts_every_test_where_phpunit_finds_it(): void {
		$this->assertNoProblems( $this->plugin_checks()->check_tests_are_discovered() );
	}

	public function test_it_reports_tests_phpunit_never_runs(): void {
		$namespace = $this->repository()->get_namespace();
		$problems  = $this->repository()
			->write( 'tests/php/Unit/FoundTest.php', $this->test_case( 'Unit', 'final class FoundTest' ) )
			->write( 'tests/php/Unit/TestCase.php', $this->test_case( 'Unit', 'abstract class TestCase' ) )
			->write( 'tests/php/Unit/Forgotten.php', $this->test_case( 'Unit', 'final class Forgotten' ) )
			->write( 'tests/php/Elsewhere/LostTest.php', $this->test_case( 'Elsewhere', 'final class LostTest' ) )
			->checks()
			->check_tests_are_discovered();

		$this->assertProblems(
			[
				'[architecture.testsAreDiscovered] tests/php/Elsewhere/LostTest.php looks like a test, but no'
					. ' PHPUnit suite in phpunit.xml.dist includes it, so it never runs. Move it into one of the'
					. ' suite directories (tests/php/Unit).',
				"[architecture.testsAreDiscovered] {$namespace}\\Tests\\Unit\\Forgotten is a test case, but"
					. ' PHPUnit never runs it from tests/php/Unit/Forgotten.php.',
			],
			$problems
		);
	}

	/**
	 * Build the source of a PHP file from its lines.
	 *
	 * @param string ...$lines Lines after the opening tag.
	 * @return string Source of the file.
	 */
	private function php( string ...$lines ): string {
		return "<?php\n\n" . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Build the source of a test case in the temporary repository.
	 *
	 * @param string $directory   Directory below tests/php, and namespace.
	 * @param string $declaration Class declaration up to its name.
	 * @return string Source of the file.
	 */
	private function test_case( string $directory, string $declaration ): string {
		return $this->php(
			'declare( strict_types=1 );',
			'',
			'namespace NAMESPACE\\Tests\\' . $directory . ';',
			'',
			$declaration . ' extends \\PHPUnit\\Framework\\TestCase {',
			'}'
		);
	}

	private function plugin_checks(): RepositoryChecks {
		return new RepositoryChecks( RepositoryLayout::of_plugin() );
	}

	private function repository(): TemporaryRepository {
		if ( $this->repository === null ) {
			$this->repository = new TemporaryRepository();
		}

		return $this->repository;
	}

	/**
	 * @param array<string> $problems Problems a check found.
	 */
	private function assertNoProblems( array $problems ): void {
		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	/**
	 * Assert that each problem starts as expected and ends with the rule's
	 * explanation.
	 *
	 * @param array<string> $expected Expected beginnings of the problems.
	 * @param array<string> $problems Problems a check found.
	 */
	private function assertProblems( array $expected, array $problems ): void {
		$this->assertCount( count( $expected ), $problems, implode( "\n\n", $problems ) );

		foreach ( array_values( $expected ) as $index => $beginning ) {
			$this->assertStringStartsWith( $beginning, $problems[ $index ] );
			$this->assertStringContainsString( 'Rule: docs/architecture-rules.md#', $problems[ $index ] );
		}
	}
}
