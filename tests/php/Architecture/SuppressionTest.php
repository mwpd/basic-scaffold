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
 * The architecture rules are not silenced.
 */
final class SuppressionTest extends TestCase {

	private const FIX = 'Fix the code instead; if the rule is wrong for a real case, open an issue with needs:'
		. ' decision';

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

	public function test_the_plugin_does_not_silence_architecture_rules(): void {
		$checks   = new SuppressionChecks( RepositoryLayout::of_plugin() );
		$problems = array_merge( $checks->check_inline_suppressions(), $checks->check_phpstan_configuration() );

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_an_inline_ignore_of_an_architecture_rule(): void {
		$problems = $this->repository()
			->write(
				'src/Service.php',
				"<?php\n\ndeclare( strict_types=1 );\n\nnamespace NAMESPACE;\n\n"
				. "// @phpstan-ignore architecture.finalByDefault\nclass Service {\n\n"
				. "\tpublic function run(): void {\n\t\trun(); // @phpstan-ignore-line architecture.noStaticState\n"
				. "\t\t// @phpstan-ignore method.notFound\n\t}\n}\n"
			)
			->write( 'tests/php/Fixture/Broken.php', "<?php\n// @phpstan-ignore architecture.finalByDefault\n" )
			->checks_for_suppression()
			->check_inline_suppressions();

		$this->assertProblems(
			[ 'src/Service.php:7 silences an architecture rule.', 'src/Service.php:11 silences an architecture rule.' ],
			$problems
		);
	}

	public function test_it_reports_ignored_architecture_errors_in_the_configuration(): void {
		$problems = $this->repository()
			->write(
				'phpstan.neon.dist',
				implode(
					"\n",
					[
						'includes:',
						'    - vendor/package/extension.neon',
						'    # The baseline holds the errors left to fix.',
						"    - 'phpstan-baseline.neon'",
						'',
						'parameters:',
						'    level: max',
						'    reportUnmatchedIgnoredErrors: false',
						'    ignoreErrors: # Temporary ignores.',
						'        # Not an architecture error.',
						"        - '#Some other error#'",
						"        - '#is not final#'",
						'        - {identifier: architecture.noStaticState}',
						'        -',
						"            message: '#^Class Acme\\\\Service is not final\\. Declare it final#'",
						'            path: src/Service.php',
						'    architecture:',
						'        namespace: Acme',
						'',
					]
				)
			)
			->write(
				'phpstan-baseline.neon',
				"parameters:\n    ignoreErrors:\n        -\n            message: '#other#'\n"
				. "            identifiers: [architecture.finalByDefault]\n            count: 1\n"
			)
			->checks_for_suppression()
			->check_phpstan_configuration();

		$this->assertProblems(
			[
				'phpstan.neon.dist turns off reportUnmatchedIgnoredErrors, so an ignore can outlive the error it hid.',
				'phpstan.neon.dist ignores errors of architecture.finalByDefault.',
				'phpstan.neon.dist ignores errors of architecture.noStaticState.',
				'phpstan.neon.dist ignores errors of architecture.finalByDefault.',
				'phpstan-baseline.neon ignores errors of architecture.finalByDefault.',
			],
			$problems
		);
	}

	private function repository(): TemporaryRepository {
		if ( $this->repository === null ) {
			$this->repository = new TemporaryRepository();
		}

		return $this->repository;
	}

	/**
	 * @param array<string> $expected Expected problems, without their prefix
	 *                                and explanation.
	 * @param array<string> $problems Problems found.
	 */
	private function assertProblems( array $expected, array $problems ): void {
		$this->assertCount( count( $expected ), $problems, implode( "\n\n", $problems ) );

		foreach ( array_values( $expected ) as $index => $problem ) {
			$this->assertStringStartsWith( '[architecture.noSuppression] ' . $problem, $problems[ $index ] );
			$this->assertStringContainsString( self::FIX, $problems[ $index ] );
			$this->assertStringContainsString(
				'Rule: docs/architecture-rules.md#architecturenosuppression',
				$problems[ $index ]
			);
		}
	}
}
