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

/**
 * Checks that the architecture rules are not silenced.
 *
 * A rule that can be switched off on the spot is not a guardrail. Exceptions
 * the maintainer accepts become configuration of the rule, where they are
 * visible and reviewed, never an ignore.
 */
final class SuppressionChecks {

	/**
	 * Layout of the repository to check.
	 */
	private RepositoryLayout $layout;

	/**
	 * Instantiate a SuppressionChecks object.
	 *
	 * @param RepositoryLayout $layout Layout of the repository to check.
	 */
	public function __construct( RepositoryLayout $layout ) {
		$this->layout = $layout;
	}

	/**
	 * No inline PHPStan ignore names an architecture rule.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_inline_suppressions(): array {
		$problems = [];
		$files    = array_merge(
			[ $this->layout->get_bootstrap() ],
			$this->layout->get_production_files(),
			$this->layout->get_view_template_files(),
			$this->layout->get_test_files()
		);

		foreach ( array_unique( $files ) as $path ) {
			if ( $this->layout->is_rule_fixture( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			// Only comments can silence PHPStan, so strings that mention an
			// ignore, such as in a test, do not count.
			foreach ( token_get_all( (string) file_get_contents( $path ) ) as $token ) {
				if ( ! is_array( $token ) || ! in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
					continue;
				}

				foreach ( explode( "\n", $token[1] ) as $offset => $line ) {
					if ( preg_match( '/@phpstan-ignore(?:-line|-next-line)?\b.*\barchitecture\.\w+/', $line ) ) {
						$problems[] = $this->problem(
							sprintf(
								'%1$s:%2$d silences an architecture rule.',
								$this->layout->relative( $path ),
								$token[2] + $offset
							)
						);
					}
				}
			}
		}

		return $problems;
	}

	/**
	 * The PHPStan configuration neither ignores architecture rules nor lets
	 * ignores outlive what they hid.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_phpstan_configuration(): array {
		$config = $this->layout->get_phpstan_config();
		if ( ! is_readable( $config ) ) {
			return [];
		}

		$problems = [];
		$contents = (string) file_get_contents( $config );

		if ( preg_match( '/^\s*reportUnmatchedIgnoredErrors:\s*false\b/m', $contents ) ) {
			$problems[] = $this->problem(
				sprintf(
					'%s turns off reportUnmatchedIgnoredErrors, so an ignore can outlive the error it hid.',
					$this->layout->relative( $config )
				)
			);
		}

		foreach ( array_merge( [ $config ], self::get_included_files( $config, $contents ) ) as $file ) {
			$text = (string) file_get_contents( $file );

			if ( preg_match( '/^(\s*)ignoreErrors:\s*\n((?:\1\s+.*\n?|\s*\n)*)/m', $text, $matches )
				&& strpos( $matches[2], 'architecture' ) !== false ) {
				$problems[] = $this->problem(
					sprintf( '%s ignores errors of an architecture rule.', $this->layout->relative( $file ) )
				);
			}
		}

		return $problems;
	}

	/**
	 * Get the configuration files a PHPStan configuration includes, such as a
	 * baseline, apart from those of installed packages.
	 *
	 * @param string $config   Path of the configuration.
	 * @param string $contents Contents of the configuration.
	 * @return array<string> Paths of the included files that exist.
	 */
	private static function get_included_files( string $config, string $contents ): array {
		$files = [];

		if ( preg_match( '/^includes:\s*\n((?:\s+-\s*.*\n?)*)/m', $contents, $matches ) ) {
			preg_match_all( '/-\s*(\S+)/', $matches[1], $includes );

			foreach ( $includes[1] as $include ) {
				$path = dirname( $config ) . '/' . $include;

				if ( strpos( $include, 'vendor/' ) !== 0 && is_readable( $path ) ) {
					$files[] = $path;
				}
			}
		}

		return $files;
	}

	/**
	 * Complete a problem with the way forward and the rule's explanation.
	 *
	 * @param string $problem What is wrong, and where.
	 * @return string Complete message.
	 */
	private function problem( string $problem ): string {
		return sprintf(
			'[%1$s] %2$s Fix the code instead; if the rule is wrong for a real case, open an issue with'
			. ' needs: decision that asks for an allowance in the rule\'s configuration. %3$s.',
			RuleManifest::NO_SUPPRESSION,
			$problem,
			RuleManifest::get_explanation( RuleManifest::NO_SUPPRESSION )
		);
	}
}
