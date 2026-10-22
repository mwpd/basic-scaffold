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
	 * Temporary files of the current test.
	 *
	 * @var array<string>
	 */
	private array $temporary_files = [];

	protected function tear_down() {
		array_map( 'unlink', $this->temporary_files );
		$this->temporary_files = [];

		parent::tear_down();
	}

	public function test_the_plugin_uses_no_development_package_at_runtime(): void {
		[ $exit_code, $output ] = $this->analyse( dirname( __DIR__, 3 ) . '/composer-dependency-analyser.php' );

		$this->assertSame( 0, $exit_code, $this->explain( $output ) );
	}

	public function test_it_reports_production_code_that_uses_a_development_package(): void {
		$source = $this->temporary_file(
			"<?php\n\ndeclare( strict_types=1 );\n\n\\Brain\\Monkey\\Functions\\when( 'is_admin' );\n"
		);
		$config = $this->temporary_file(
			"<?php\n\nreturn ( new \\ShipMonk\\ComposerDependencyAnalyser\\Config\\Configuration() )\n"
			. "\t->disableComposerAutoloadPathScan()\n"
			. "\t->addPathToScan( '" . addslashes( $source ) . "', false );\n"
		);

		[ $exit_code, $output ] = $this->analyse( $config );

		$this->assertNotSame( 0, $exit_code, $output );
		$this->assertStringContainsString( 'brain/monkey', $output );
		$this->assertStringContainsString( basename( $source ), $output );
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

	/**
	 * Write a temporary PHP file.
	 *
	 * @param string $contents Contents of the file.
	 * @return string Path of the file.
	 */
	private function temporary_file( string $contents ): string {
		$path = sys_get_temp_dir() . '/' . uniqid( 'mwpd-architecture-', false ) . '.php';

		file_put_contents( $path, $contents );
		$this->temporary_files[] = $path;

		return $path;
	}
}
