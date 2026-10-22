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
 * The code that runs in production uses no development package.
 *
 * composer-dependency-analyser does the analysis, configured by
 * composer-dependency-analyser.php in the plugin root to look only at the
 * main file, src/ and views/, and only for development packages.
 */
final class RuntimeDependenciesTest extends TestCase {

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

	public function test_the_plugin_uses_no_development_package_at_runtime(): void {
		[ $exit_code, $output ] = $this->analyse( dirname( __DIR__, 3 ) . '/composer-dependency-analyser.php' );

		$this->assertSame( 0, $exit_code, $this->explain( $output ) );
	}

	public function test_it_reports_production_code_that_uses_a_development_package(): void {
		$this->repository = ( new TemporaryRepository() )
			->write(
				'src/Service.php',
				"<?php\n\ndeclare( strict_types=1 );\n\nnamespace NAMESPACE;\n\n"
				. "final class Service {\n\n\tpublic function make(): object {\n"
				. "\t\treturn new \\Brain\\Monkey\\Container();\n\t}\n}\n"
			)
			->write(
				'views/notice.php',
				"<?php \\Mockery::mock( 'Notice' ); ?>\n"
			);

		// The plugin's configuration, applied to the temporary repository.
		$config = $this->repository->get_root() . '/composer-dependency-analyser.php';
		file_put_contents(
			$config,
			sprintf(
				implode(
					"\n",
					[
						'<?php',
						"require_once '%1\$s';",
						'return \\%2$s::for_layout(',
						"	new \\%3\$s( '%4\$s', 'plugin.php', [ 'src' ], [ 'views' ], 'tests', [], [] )",
						');',
					]
				),
				addslashes( dirname( __DIR__, 3 ) . '/vendor/autoload.php' ),
				RuntimeDependencyConfiguration::class,
				RepositoryLayout::class,
				addslashes( $this->repository->get_root() )
			)
		);

		[ $exit_code, $output ] = $this->analyse( $config );
		$message                = $this->explain( $output );

		$this->assertNotSame( 0, $exit_code, $output );
		$this->assertStringContainsString( 'brain/monkey', $message );
		$this->assertStringContainsString( 'src/Service.php', $message );
		$this->assertStringContainsString( 'mockery/mockery', $message );
		$this->assertStringContainsString( 'views/notice.php', $message );
		$this->assertStringContainsString( 'move the code into the tests, or bundle', $message );
	}

	/**
	 * Run composer-dependency-analyser on the plugin's composer.json.
	 *
	 * @param string $config Path of the configuration file to use.
	 * @return array{0: int, 1: string} Exit code and output.
	 */
	private function analyse( string $config ): array {
		$root    = dirname( __DIR__, 3 );
		$command = sprintf(
			'%s %s --composer-json=%s --config=%s 2>&1',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $root . '/vendor/bin/composer-dependency-analyser' ),
			escapeshellarg( $root . '/composer.json' ),
			escapeshellarg( $config )
		);

		exec( $command, $lines, $exit_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

		return [ $exit_code, implode( "\n", $lines ) ];
	}

	/**
	 * Explain a failure in the three parts of every architecture failure.
	 *
	 * @param string $output Output of composer-dependency-analyser.
	 * @return string Failure message.
	 */
	private function explain( string $output ): string {
		return sprintf(
			'[%1$s] Code that runs in production uses a development package:%2$s%3$s%2$sThe plugin runs on PHP'
			. ' and WordPress alone; move the code into the tests, or bundle what it needs into src/. %4$s.',
			RuleManifest::NO_RUNTIME_DEPENDENCIES,
			"\n",
			$output,
			RuleManifest::get_explanation( RuleManifest::NO_RUNTIME_DEPENDENCIES )
		);
	}
}
