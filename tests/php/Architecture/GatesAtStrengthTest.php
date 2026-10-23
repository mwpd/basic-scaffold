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
 * The quality gates keep the strength promised by the hard rules.
 */
final class GatesAtStrengthTest extends TestCase {

	private const EXPLANATION = ' Fix the code instead of weakening the gate; restore the required configuration.'
		. ' Hard rule: do not weaken the gates, see AGENTS.md#hard-rules.'
		. ' Rule: docs/architecture-rules.md#architecturegatesatstrength.';

	private ?TemporaryRepository $repository = null;

	protected function tear_down() {
		if ( $this->repository !== null ) {
			$this->repository->delete();
			$this->repository = null;
		}

		parent::tear_down();
	}

	public function test_the_plugin_keeps_its_quality_gates_at_strength(): void {
		$problems = ( new GatesAtStrengthChecks( RepositoryLayout::of_plugin() ) )->check_strength();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	/**
	 * @dataProvider weakened_configurations
	 * @param string $file     Configuration to replace.
	 * @param string $contents Weakened configuration.
	 * @param string $problem  Expected problem, without its identifier and explanation.
	 */
	public function test_it_reports_a_weakened_gate( string $file, string $contents, string $problem ): void {
		$repository = $this->repository()->write( $file, $contents );
		$problems   = ( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength();

		$this->assertProblems( [ $problem ], $problems );
	}

	/**
	 * @return array<string, array{string, string, string}> Configuration and full problem for each condition.
	 */
	public static function weakened_configurations(): array {
		$cases = [
			'PHPStan level'             => [
				'phpstan.neon.dist',
				"parameters:\n    level: 9\n",
				'phpstan.neon.dist must set PHPStan level to max.',
			],
			'PHPStan missing level'     => [
				'phpstan.neon.dist',
				'parameters: {}',
				'phpstan.neon.dist must set PHPStan level to max.',
			],
			'PHPStan baseline'          => [
				'phpstan.neon.dist',
				"includes!:\n    - 'phpstan-baseline.neon' # Not an ordinary extension.\nparameters:\n    level: max\n",
				'phpstan.neon.dist includes a PHPStan baseline (phpstan-baseline.neon). Remove the baseline include.',
			],
			'Psalm level'               => [
				'psalm.xml',
				'<psalm errorLevel="3"/>',
				'psalm.xml must set Psalm errorLevel to an integer from 1 to 2.',
			],
			'Psalm missing level'       => [
				'psalm.xml',
				'<psalm/>',
				'psalm.xml must set Psalm errorLevel to an integer from 1 to 2.',
			],
			'Psalm invalid level'       => [
				'psalm.xml',
				'<psalm errorLevel="2oops"/>',
				'psalm.xml must set Psalm errorLevel to an integer from 1 to 2.',
			],
			'Psalm baseline'            => [
				'psalm.xml',
				'<psalm xmlns="https://getpsalm.org/schema/config" errorLevel="2" errorBaseline="baseline.xml"/>',
				'psalm.xml sets errorBaseline. Remove the Psalm baseline attribute.',
			],
			'Psalm empty baseline'      => [
				'psalm.xml',
				'<psalm errorLevel="2" errorBaseline=""/>',
				'psalm.xml sets errorBaseline. Remove the Psalm baseline attribute.',
			],
			'PHPCS WordPress'           => [
				'phpcs.xml.dist',
				'<ruleset><!-- <rule ref="WordPress"/> --><rule ref="PHPCompatibility"/>'
					. '<config name="testVersion" value="7.4-"/></ruleset>',
				'phpcs.xml.dist must reference the WordPress standard.',
			],
			'PHPCS PHPCompatibility'    => [
				'phpcs.xml.dist',
				'<ruleset><rule ref="WordPress"/><config name="testVersion" value="7.4-"/></ruleset>',
				'phpcs.xml.dist must reference the PHPCompatibility standard.',
			],
			'PHPCS compatibility floor' => [
				'phpcs.xml.dist',
				'<ruleset><rule ref="WordPress"/><rule ref="PHPCompatibility"/>'
					. '<config name="testVersion" value="8.0-"/></ruleset>',
				'phpcs.xml.dist must set testVersion to 7.4-.',
			],
			'PHPCS missing floor'       => [
				'phpcs.xml.dist',
				'<ruleset><rule ref="WordPress"/><rule ref="PHPCompatibility"/></ruleset>',
				'phpcs.xml.dist must set testVersion to 7.4-.',
			],
		];

		foreach ( [ 'unit', 'integration', 'architecture', 'phpstan-rules' ] as $missing ) {
			$suites = '';
			foreach ( [ 'unit', 'integration', 'architecture', 'phpstan-rules' ] as $suite ) {
				if ( $suite !== $missing ) {
					$suites .= '<testsuite name="' . $suite . '"/>';
				}
			}

			$cases[ 'PHPUnit ' . $missing ] = [
				'phpunit.xml.dist',
				'<phpunit><testsuites>' . $suites . '</testsuites></phpunit>',
				'phpunit.xml.dist must declare the ' . $missing . ' test suite.',
			];
		}

		return $cases;
	}

	/**
	 * @dataProvider configuration_files
	 * @param string $file Configuration to remove.
	 */
	public function test_it_reports_a_missing_configuration( string $file ): void {
		$repository = $this->repository();
		unlink( $repository->get_root() . '/' . $file );

		$this->assertProblems(
			[ $file . ' cannot be read. Restore a readable configuration file.' ],
			( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength()
		);
	}

	/**
	 * @dataProvider configuration_files
	 * @param string $file Configuration replaced by a directory, which is not a readable file.
	 */
	public function test_it_reports_a_configuration_that_is_not_a_file( string $file ): void {
		$repository = $this->repository();
		$path       = $repository->get_root() . '/' . $file;
		unlink( $path );
		mkdir( $path );

		$this->assertProblems(
			[ $file . ' cannot be read. Restore a readable configuration file.' ],
			( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength()
		);
	}

	/**
	 * @dataProvider configuration_files
	 * @param string $file Configuration to corrupt.
	 */
	public function test_it_reports_a_malformed_configuration( string $file ): void {
		$repository = $this->repository()->write( $file, $file === 'phpstan.neon.dist' ? 'parameters: [' : '<broken>' );

		$this->assertProblems(
			[ $file . ' cannot be parsed. Restore a valid configuration file.' ],
			( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength()
		);
	}

	/**
	 * @return array<string, array{string}> Gate configuration files.
	 */
	public static function configuration_files(): array {
		return [
			'PHPStan' => [ 'phpstan.neon.dist' ],
			'Psalm'   => [ 'psalm.xml' ],
			'PHPCS'   => [ 'phpcs.xml.dist' ],
			'PHPUnit' => [ 'phpunit.xml.dist' ],
		];
	}

	public function test_it_accepts_stronger_psalm_and_ordinary_phpstan_includes(): void {
		$repository = $this->repository()
			->write( 'psalm.xml', '<psalm errorLevel="1"><issueHandlers/></psalm>' )
			->write(
				'phpstan.neon.dist',
				"includes:\n    - extension.neon\n    - vendor/package/extension.neon\nparameters:\n    level: 'max'\n"
				. "    ignoreErrors:\n        - '#existing ignore#'\n"
			)
			->write( 'extension.neon', "includes:\n    - phpstan.neon.dist\nparameters: {}\n" );

		$this->assertSame( [], ( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength() );
	}

	public function test_it_reports_a_baseline_in_a_nested_include(): void {
		$repository = $this->repository()
			->write( 'phpstan.neon.dist', "includes: [config/extension.neon]\nparameters: {level: max}\n" )
			->write( 'config/extension.neon', 'includes: [%currentWorkingDirectory%/phpstan-baseline.neon]' );

		$this->assertProblems(
			[
				'config/extension.neon includes a PHPStan baseline (%currentWorkingDirectory%/phpstan-baseline.neon).'
					. ' Remove the baseline include.',
			],
			( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength()
		);
	}

	public function test_it_reports_a_generated_baseline_with_a_different_filename(): void {
		$repository = $this->repository()
			->write( 'phpstan.neon.dist', "includes: [remaining.neon]\nparameters: {level: max}\n" )
			->write(
				'remaining.neon',
				"parameters:\n    ignoreErrors:\n        - {message: '#old error#', count: 1, path: src/Service.php}\n"
			);

		$this->assertProblems(
			[ 'remaining.neon contains counted baseline errors. Remove the PHPStan baseline include.' ],
			( new GatesAtStrengthChecks( $repository->get_layout() ) )->check_strength()
		);
	}

	private function repository(): TemporaryRepository {
		if ( $this->repository === null ) {
			$this->repository = new TemporaryRepository();
			$this->repository
				->write( 'phpstan.neon.dist', "parameters:\n    level: max\n" )
				->write( 'psalm.xml', '<psalm errorLevel="2"/>' )
				->write(
					'phpcs.xml.dist',
					'<ruleset><rule ref="WordPress"/><rule ref="PHPCompatibility"/>'
						. '<config name="testVersion" value="7.4-"/></ruleset>'
				)
				->write(
					'phpunit.xml.dist',
					'<phpunit><testsuites><testsuite name="unit"/><testsuite name="integration"/>'
						. '<testsuite name="architecture"/><testsuite name="phpstan-rules"/></testsuites></phpunit>'
				);
		}

		return $this->repository;
	}

	/**
	 * @param array<string> $expected Expected problems, without their identifier and explanation.
	 * @param array<string> $problems Problems found.
	 */
	private function assertProblems( array $expected, array $problems ): void {
		$this->assertSame(
			array_map(
				static fn( string $problem ): string => '[architecture.gatesAtStrength] '
					. $problem . self::EXPLANATION,
				$expected
			),
			$problems
		);
	}
}
