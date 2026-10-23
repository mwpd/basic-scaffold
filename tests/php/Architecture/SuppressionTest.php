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

	public function test_the_plugin_names_every_inline_suppression(): void {
		$checks   = new SuppressionChecks( RepositoryLayout::of_plugin() );
		$problems = $checks->check_named_suppressions();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	/**
	 * @dataProvider unnamed_suppressions
	 *
	 * @param string $directive Suppression written in a comment.
	 * @param string $fix       Narrow form to recommend.
	 */
	public function test_it_reports_each_unnamed_suppression_with_the_full_message(
		string $directive,
		string $fix
	): void {
		$problems = $this->repository()
			->write( 'src/Service.php', "<?php\n/**\n * Example.\n * " . $directive . "\n */\n" )
			->checks_for_suppression()
			->check_named_suppressions();

		$this->assertSame( [ self::named_problem( 'src/Service.php:4', $directive, $fix ) ], $problems );
	}

	/**
	 * @return array<string, array{string, string}> Directives and their narrow alternatives.
	 */
	public static function unnamed_suppressions(): array {
		$phpstan = '// @phpstan-ignore argument.type (reason)';
		$phpcs   = '// phpcs:ignore Category.Sniff -- reason';
		$psalm   = '@psalm-suppress IssueType reason';

		return [
			'PHPStan line'                => [ '@phpstan-ignore-line', $phpstan ],
			'PHPStan next line'           => [ '@phpstan-ignore-next-line', $phpstan ],
			'PHPStan line with a name'    => [ '@phpstan-ignore-line argument.type', $phpstan ],
			'PHPStan next line with name' => [ '@phpstan-ignore-next-line argument.type', $phpstan ],
			'PHPStan missing identifier'  => [ '@phpstan-ignore', $phpstan ],
			'PHPStan reason only'         => [ '@phpstan-ignore (argument.type is a false positive)', $phpstan ],
			'PHPStan undotted name'       => [ '@phpstan-ignore argument', $phpstan ],
			'PHPCS ignore'                => [ 'phpcs:ignore', $phpcs ],
			'PHPCS disable'               => [ 'phpcs:disable', $phpcs ],
			'PHPCS prefixed ignore'       => [ '@phpcs:ignore', $phpcs ],
			'PHPCS prefixed disable'      => [ '@phpcs:disable', $phpcs ],
			'PHPCS ignore reason only'    => [ 'phpcs:ignore -- Category.Sniff is a false positive', $phpcs ],
			'PHPCS disable reason only'   => [ '@phpcs:disable -- Category.Sniff is a false positive', $phpcs ],
			'PHPCS undotted name'         => [ 'phpcs:ignore WordPress', $phpcs ],
			'PHPCS ignore file'           => [ 'phpcs:ignoreFile', $phpcs ],
			'PHPCS prefixed ignore file'  => [ '@phpcs:ignoreFile', $phpcs ],
			'PHPCS named ignore file'     => [ 'phpcs:ignoreFile WordPress.Security.EscapeOutput', $phpcs ],
			'PHPCS prefixed named file'   => [ '@phpcs:ignoreFile Category.Sniff -- reason', $phpcs ],
			'Psalm missing issue'         => [ '@psalm-suppress', $psalm ],
		];
	}

	public function test_it_accepts_named_suppressions_and_out_of_scope_directives(): void {
		$source = implode(
			"\n",
			[
				'<?php',
				'// @phpstan-ignore argument.type',
				'// @phpstan-ignore argument.type, method.notFound (reason)',
				'/* @phpstan-ignore argument.type (reason) */',
				'// phpcs:ignore WordPress.Security.EscapeOutput',
				'// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reason',
				'// @phpcs:disable Category.Sniff, Other.Sniff.code -- reason',
				'// phpcs:disable Category.Sniff',
				'/** @psalm-suppress UnresolvableInclude */',
				'// @psalm-suppress UnresolvableInclude reason',
				'// @phpstan-ignore-file',
				'// phpcs:enable',
				'// @phpcs:enable',
				'// phpcs:set Category.Sniff property value',
			]
		);

		$problems = $this->repository()
			->write( 'src/Service.php', $source )
			->checks_for_suppression()
			->check_named_suppressions();

		$this->assertSame( [], $problems );
	}

	public function test_it_reports_each_occurrence_on_its_comment_line(): void {
		$phpstan  = '// @phpstan-ignore argument.type (reason)';
		$problems = $this->repository()
			->write(
				'src/Service.php',
				"<?php\n// @phpstan-ignore-line @phpstan-ignore-next-line\n"
				. "/*\n * phpcs:ignore\n * @psalm-suppress\n */\n"
			)
			->checks_for_suppression()
			->check_named_suppressions();

		$this->assertSame(
			[
				self::named_problem( 'src/Service.php:2', '@phpstan-ignore-line', $phpstan ),
				self::named_problem( 'src/Service.php:2', '@phpstan-ignore-next-line', $phpstan ),
				self::named_problem( 'src/Service.php:4', 'phpcs:ignore', '// phpcs:ignore Category.Sniff -- reason' ),
				self::named_problem( 'src/Service.php:5', '@psalm-suppress', '@psalm-suppress IssueType reason' ),
			],
			$problems
		);
	}

	public function test_it_checks_the_bootstrap_sources_views_and_tests(): void {
		$paths      = [
			'plugin.php',
			'src/Service.php',
			'views/example.php',
			'tests/php/Unit/ExampleTest.php',
		];
		$repository = $this->repository();
		$expected   = [];

		foreach ( $paths as $path ) {
			$repository->write( $path, "<?php\n// phpcs:ignore\n" );
			$expected[] = self::named_problem(
				$path . ':2',
				'phpcs:ignore',
				'// phpcs:ignore Category.Sniff -- reason'
			);
		}

		$problems = $repository->checks_for_suppression()->check_named_suppressions();
		sort( $expected );
		sort( $problems );

		$this->assertSame( $expected, $problems );
	}

	public function test_it_ignores_suppression_strings_and_rule_fixtures(): void {
		$repository = $this->repository();

		foreach ( self::unnamed_suppressions() as [ $directive ] ) {
			$repository->write( 'src/Service.php', "<?php\n\$example = " . (string) json_encode( $directive ) . ";\n" );
			$this->assertSame( [], $repository->checks_for_suppression()->check_named_suppressions() );
		}

		$repository->write( 'tests/php/PHPStan/Fixture/Broken.php', "<?php\n// phpcs:ignore\n" );
		$layout = new RepositoryLayout(
			$repository->get_root(),
			'plugin.php',
			[ 'src' ],
			[ 'views' ],
			'tests',
			[],
			[ 'tests/php/PHPStan/Fixture' ]
		);

		$this->assertSame( [], ( new SuppressionChecks( $layout ) )->check_named_suppressions() );
	}

	/**
	 * Build the complete expected message independently of the manifest.
	 *
	 * @param string $location  Relative file and line number.
	 * @param string $directive Suppression found in the comment.
	 * @param string $fix       Narrow form to recommend.
	 * @return string Complete failure message.
	 */
	private static function named_problem( string $location, string $directive, string $fix ): string {
		return '[architecture.suppressionsAreNamed] ' . $location . ' uses a broad or unnamed suppression ('
			. explode( ' ', $directive )[0] . '). Use ' . $fix
			. '; a reason is recommended. Hard rule: do not weaken the gates, see AGENTS.md#hard-rules.'
			. ' Rule: docs/architecture-rules.md#architecturesuppressionsarenamed.';
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
						'    - %currentWorkingDirectory%/more.neon',
						'',
						'parameters:',
						'    level: max',
						'    reportUnmatchedIgnoredErrors: false',
						'    ignoreErrors: # Temporary ignores.',
						'        # Not an architecture error.',
						"        - '#Some other error#'",
						"        - '#is not final#'",
						'        - {identifier: architecture.noStaticState}',
						"        - {identifier: class.notFound, message: '#not#'}",
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
				'more.neon',
				"parameters:\n    ignoreErrors!:\n        - {identifier: architecture.prefixedNames}\n"
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
				'more.neon ignores errors of architecture.prefixedNames.',
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
