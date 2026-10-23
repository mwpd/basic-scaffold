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
 * Every gate covers the declared production code, templates and tests.
 */
final class EveryPathAnalysedTest extends TestCase {

	private const EXPLANATION = ' Fix the code instead of weakening the gate; restore the required configuration.'
		. ' Hard rule: do not weaken the gates, see AGENTS.md#hard-rules.'
		. ' Rule: docs/architecture-rules.md#architectureeverypathanalysed.';

	private ?TemporaryRepository $repository = null;

	protected function tear_down() {
		if ( $this->repository !== null ) {
			$this->repository->delete();
			$this->repository = null;
		}

		parent::tear_down();
	}

	public function test_the_plugin_analyses_every_required_path(): void {
		$problems = ( new EveryPathAnalysedChecks( RepositoryLayout::of_plugin() ) )->check_paths();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_accepts_a_repository_with_all_paths_covered(): void {
		$this->assertSame( [], ( new EveryPathAnalysedChecks( $this->repository()->get_layout() ) )->check_paths() );
	}

	/**
	 * @dataProvider omitted_paths
	 * @param string $gate    Gate whose paths are incomplete.
	 * @param string $missing Omitted path.
	 */
	public function test_it_reports_an_omitted_path( string $gate, string $missing ): void {
		$paths      = array_values( array_diff( [ 'plugin.php', 'src', 'views', 'tests' ], [ $missing ] ) );
		$repository = $this->repository()->write( self::file( $gate ), self::configuration( $gate, $paths ) );
		$setting    = [
			'PHPStan' => 'parameters.paths',
			'Psalm'   => 'projectFiles',
			'PHPCS'   => '<file> entries',
		];

		$this->assertProblems(
			[
				$gate . ' does not analyse ' . $missing . '. Add it to ' . $setting[ $gate ]
					. ' in ' . self::file( $gate ) . '.',
			],
			( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths()
		);
	}

	/**
	 * @return array<string, array{string, string}> Missing roots, including ones not created on disk.
	 */
	public static function omitted_paths(): array {
		$cases = [];
		foreach ( [ 'PHPStan', 'PHPCS', 'Psalm' ] as $gate ) {
			foreach ( [ 'src', 'plugin.php', 'tests', 'views' ] as $path ) {
				$cases[ $gate . ' omits ' . $path ] = [ $gate, $path ];
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider excluded_paths
	 * @param string $gate     Gate with an exclusion.
	 * @param string $excluded Excluded path or glob.
	 * @param string $required Required root affected by the exclusion.
	 */
	public function test_it_reports_an_unpermitted_exclusion( string $gate, string $excluded, string $required ): void {
		$repository = $this->repository()->write(
			self::file( $gate ),
			self::configuration( $gate, [ 'plugin.php', 'src', 'views', 'tests' ], [ $excluded ] )
		);
		$setting    = [
			'PHPStan' => 'parameters.excludePaths',
			'Psalm'   => 'projectFiles/ignoreFiles',
			'PHPCS'   => 'global <exclude-pattern> entries',
		];

		$this->assertProblems(
			[
				$gate . ' excludes ' . $required . ' through ' . $excluded . '. Remove it from ' . $setting[ $gate ]
					. ' in ' . self::file( $gate ) . '.',
			],
			( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths()
		);
	}

	/**
	 * @return array<string, array{string, string, string}> Exclusions that would hide required code.
	 */
	public static function excluded_paths(): array {
		$cases = [];
		foreach ( [ 'PHPStan', 'PHPCS', 'Psalm' ] as $gate ) {
			foreach ( [
				'plugin.php'                     => 'plugin.php',
				'src'                            => 'src',
				'sr?'                            => 'src',
				'views/*'                        => 'views',
				'tests'                          => 'tests',
				'src/Hidden.php'                 => 'src',
				'src/*.php'                      => 'src',
				'src/*/Hidden.php'               => 'src',
				'tests/php/FixtureSibling/*.php' => 'tests',
				'tests/php/Fixture*/*.php'       => 'tests',
				'tests/php/*/Hidden.php'         => 'tests',
			] as $excluded => $required ) {
				$cases[ $gate . ' excludes ' . $excluded ] = [ $gate, $excluded, $required ];
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider gates
	 * @param string $gate Gate whose permitted exclusions are exercised.
	 */
	public function test_it_accepts_fixture_exclusions_and_unrelated_paths( string $gate ): void {
		$repository = $this->repository()->write(
			self::file( $gate ),
			self::configuration(
				$gate,
				[ '.' ],
				[
					'tests/php/Fixture',
					'tests/php/Fixture/*.php',
					'vendor/*',
					'src-sibling/*',
					'plugin.php/Hidden.php',
				]
			)
		);

		$this->assertSame( [], ( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths() );
	}

	public function test_it_checks_each_declared_production_and_view_directory(): void {
		$repository = $this->repository();
		$layout     = new RepositoryLayout(
			$repository->get_root(),
			'plugin.php',
			[ 'src', 'lib' ],
			[ 'views', 'templates' ],
			'tests',
			[],
			[ 'tests/php/Fixture' ]
		);

		$this->assertProblems(
			[
				'PHPStan does not analyse lib. Add it to parameters.paths in phpstan.neon.dist.',
				'PHPStan does not analyse templates. Add it to parameters.paths in phpstan.neon.dist.',
				'Psalm does not analyse lib. Add it to projectFiles in psalm.xml.',
				'Psalm does not analyse templates. Add it to projectFiles in psalm.xml.',
				'PHPCS does not analyse lib. Add it to <file> entries in phpcs.xml.dist.',
				'PHPCS does not analyse templates. Add it to <file> entries in phpcs.xml.dist.',
			],
			( new EveryPathAnalysedChecks( $layout ) )->check_paths()
		);
	}

	public function test_it_accepts_documented_exclusions_and_rule_scoped_phpcs_patterns(): void {
		$repository = $this->repository()
			->write(
				'phpstan.neon.dist',
				self::configuration( 'PHPStan', [ '.' ], [ 'tests/php/Fixture/views/broken-view.php' ] )
			)
			->write(
				'psalm.xml',
				self::configuration(
					'Psalm',
					[ '.' ],
					[ 'tests/php/PHPStan', 'tests/php/Fixture/views/broken-view.php' ]
				)
			)
			->write(
				'phpcs.xml.dist',
				'<ruleset><file>.</file><rule ref="WordPress">'
					. '<exclude-pattern>/src/*</exclude-pattern></rule></ruleset>'
			);

		$this->assertSame( [], ( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths() );
	}

	public function test_it_resolves_phpstan_paths_and_both_exclusion_map_entries(): void {
		$repository = $this->repository()->write(
			'phpstan.neon.dist',
			"parameters:\n    paths: [%currentWorkingDirectory%/src/..]\n    excludePaths:\n"
				. "        analyse: [tests/php/Fixture/*]\n        analyseAndScan: [tests/php/Fixture]\n"
		);

		$this->assertSame( [], ( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths() );

		foreach ( [ 'analyse', 'analyseAndScan' ] as $key ) {
			$repository->write(
				'phpstan.neon.dist',
				"parameters:\n    paths: [plugin.php, src, views, tests]\n"
					. "    excludePaths:\n        " . $key . ': [src]'
			);
			$this->assertProblems(
				[
					'PHPStan excludes src through src.'
						. ' Remove it from parameters.excludePaths in phpstan.neon.dist.',
				],
				( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths()
			);
		}
	}

	/**
	 * @dataProvider gates
	 * @param string $gate Gate whose configuration is unavailable or malformed.
	 */
	public function test_it_reports_configuration_read_and_parse_failures( string $gate ): void {
		$repository = $this->repository();
		$file       = self::file( $gate );
		$path       = $repository->get_root() . '/' . $file;
		unlink( $path );

		foreach ( [ false, true ] as $directory ) {
			if ( $directory ) {
				mkdir( $path );
			}
			$this->assertProblems(
				[ $gate . ' configuration ' . $file . ' cannot be read. Restore a readable configuration file.' ],
				( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths()
			);
		}
		rmdir( $path );

		foreach ( $gate === 'PHPStan' ? [ 'parameters: [', 'scalar' ] : [ '<broken>', '<wrong/>' ] as $contents ) {
			$repository->write( $file, $contents );
			$this->assertProblems(
				[ $gate . ' configuration ' . $file . ' cannot be parsed. Restore a valid configuration file.' ],
				( new EveryPathAnalysedChecks( $repository->get_layout() ) )->check_paths()
			);
		}
	}

	/**
	 * @return array<string, array{string}> Gates that must cover every path.
	 */
	public static function gates(): array {
		return [
			'PHPStan' => [ 'PHPStan' ],
			'Psalm'   => [ 'Psalm' ],
			'PHPCS'   => [ 'PHPCS' ],
		];
	}

	private function repository(): TemporaryRepository {
		if ( $this->repository === null ) {
			$this->repository = new TemporaryRepository();
			foreach ( [ 'PHPStan', 'Psalm', 'PHPCS' ] as $gate ) {
				$this->repository->write(
					self::file( $gate ),
					self::configuration( $gate, [ 'plugin.php', 'src', 'views', 'tests' ] )
				);
			}
		}

		return $this->repository;
	}

	private static function file( string $gate ): string {
		return [
			'PHPStan' => 'phpstan.neon.dist',
			'Psalm'   => 'psalm.xml',
			'PHPCS'   => 'phpcs.xml.dist',
		][ $gate ];
	}

	/**
	 * @param string        $gate     Gate to configure.
	 * @param array<string> $paths    Included roots.
	 * @param array<string> $excluded Excluded paths or patterns.
	 * @return string Minimal configuration, with Psalm's namespace and PHPCS's leading slashes.
	 */
	private static function configuration( string $gate, array $paths, array $excluded = [] ): string {
		if ( $gate === 'PHPStan' ) {
			return "parameters:\n    paths: [" . implode( ', ', $paths ) . "]\n    excludePaths: ["
				. implode( ', ', $excluded ) . "]\n";
		}

		$includes = '';
		$excludes = '';
		foreach ( $paths as $path ) {
			$element   = substr( $path, -4 ) === '.php' ? 'file' : 'directory';
			$includes .= $gate === 'Psalm' ? '<' . $element . ' name="' . $path . '"/>' : '<file>' . $path . '</file>';
		}
		foreach ( $excluded as $path ) {
			$element   = substr( $path, -4 ) === '.php' ? 'file' : 'directory';
			$excludes .= $gate === 'Psalm' ? '<' . $element . ' name="' . $path . '"/>'
				: '<exclude-pattern>/' . $path . '</exclude-pattern>';
		}

		return $gate === 'Psalm'
			? '<psalm xmlns="https://getpsalm.org/schema/config"><projectFiles>' . $includes . '<ignoreFiles>'
				. $excludes . '</ignoreFiles></projectFiles></psalm>'
			: '<ruleset>' . $includes . $excludes . '</ruleset>';
	}

	/**
	 * @param array<string> $expected Expected problems, without their identifier and explanation.
	 * @param array<string> $problems Problems found.
	 */
	private function assertProblems( array $expected, array $problems ): void {
		$this->assertSame(
			array_map(
				static fn( string $problem ): string => '[architecture.everyPathAnalysed] '
					. $problem . self::EXPLANATION,
				$expected
			),
			$problems
		);
	}
}
