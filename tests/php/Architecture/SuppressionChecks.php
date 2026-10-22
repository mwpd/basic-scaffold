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

use Nette\Neon\Neon;

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
		$files    = $this->collect_configuration( $config, [] );

		foreach ( $files as $file => $decoded ) {
			$parameters = is_array( $decoded['parameters'] ?? null ) ? $decoded['parameters'] : [];

			if ( ( $parameters['reportUnmatchedIgnoredErrors'] ?? true ) === false ) {
				$problems[] = $this->problem(
					sprintf(
						'%s turns off reportUnmatchedIgnoredErrors, so an ignore can outlive the error it hid.',
						$this->layout->relative( $file )
					)
				);
			}

			$ignores = is_array( $parameters['ignoreErrors'] ?? null ) ? $parameters['ignoreErrors'] : [];
			foreach ( $ignores as $ignore ) {
				$identifier = self::find_ignored_rule( $ignore );

				if ( $identifier !== null ) {
					$problems[] = $this->problem(
						sprintf(
							'%1$s ignores errors of %2$s.',
							$this->layout->relative( $file ),
							$identifier
						)
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Decode a configuration and the configuration files it includes, such
	 * as a baseline, apart from those of installed packages.
	 *
	 * @param string                      $file  Path of the configuration.
	 * @param array<string, array<mixed>> $found Configurations decoded so far.
	 * @return array<string, array<mixed>> Decoded configurations, keyed by path.
	 */
	private function collect_configuration( string $file, array $found ): array {
		/**
		 * Neon::decodeFile() declares a native mixed return type, which Psalm
		 * reads as a reserved word when it targets PHP 7.4.
		 *
		 * @psalm-suppress ReservedWord
		 */
		$decoded        = self::normalize_keys( Neon::decodeFile( $file ) );
		$found[ $file ] = $decoded;
		$includes       = is_array( $decoded['includes'] ?? null ) ? $decoded['includes'] : [];

		foreach ( $includes as $include ) {
			$path = is_string( $include ) ? $this->resolve_include( $include, $file ) : null;

			if ( $path !== null && is_readable( $path ) && ! isset( $found[ $path ] ) ) {
				$found = $this->collect_configuration( $path, $found );
			}
		}

		return $found;
	}

	/**
	 * Resolve an included file the way PHPStan does.
	 *
	 * @param string $included Included path, as written.
	 * @param string $file     Configuration that includes it.
	 * @return string|null Absolute path, or null for a file of an installed
	 *                     package.
	 */
	private function resolve_include( string $included, string $file ): ?string {
		$root = dirname( $this->layout->get_phpstan_config() );
		$path = str_replace( [ '%currentWorkingDirectory%', '%rootDir%' ], $root, $included );

		if ( strpos( $path, '/' ) !== 0 ) {
			$path = dirname( $file ) . '/' . $path;
		}

		return strpos( $path, $root . '/vendor/' ) === 0 ? null : $path;
	}

	/**
	 * Drop the `!` that tells NEON to replace a value rather than merge it.
	 *
	 * @param mixed $value Decoded configuration.
	 * @return array<mixed> Configuration with plain keys.
	 */
	private static function normalize_keys( $value ): array {
		$normalized = [];

		foreach ( is_array( $value ) ? $value : [] as $key => $item ) {
			$normalized[ is_string( $key ) ? rtrim( $key, '!' ) : $key ] = is_array( $item )
				? self::normalize_keys( $item )
				: $item;
		}

		return $normalized;
	}

	/**
	 * Find the architecture rule an ignoreErrors entry ignores.
	 *
	 * An entry ignores a rule by naming its identifier, or with a message
	 * pattern that matches a fixed phrase of the rule's messages.
	 *
	 * @param mixed $ignore Entry of ignoreErrors.
	 * @return string|null Identifier of the rule, or null.
	 */
	private static function find_ignored_rule( $ignore ): ?string {
		$entry       = is_array( $ignore ) ? $ignore : [ 'message' => $ignore ];
		$identifiers = array_merge( (array) ( $entry['identifier'] ?? [] ), (array) ( $entry['identifiers'] ?? [] ) );
		$patterns    = array_merge( (array) ( $entry['message'] ?? [] ), (array) ( $entry['messages'] ?? [] ) );
		$raw         = (array) ( $entry['rawMessage'] ?? [] );

		foreach ( $identifiers as $identifier ) {
			if ( is_string( $identifier ) && strpos( $identifier, 'architecture.' ) === 0 ) {
				return $identifier;
			}
		}

		// An entry limited to other identifiers cannot hide an architecture
		// error, whatever its message says.
		if ( $identifiers !== [] ) {
			return null;
		}

		// A pattern either is a fragment of the message, or spells the whole
		// message out, so it either matches the phrase or contains it.
		$texts = $raw;
		foreach ( $patterns as $pattern ) {
			if ( is_string( $pattern ) ) {
				$texts[] = stripslashes( substr( $pattern, 1, -1 ) );
			}
		}

		foreach ( RuleManifest::get_rules() as $identifier => $rule ) {
			foreach ( $rule['signatures'] as $signature ) {
				foreach ( $patterns as $pattern ) {
					// An invalid pattern ignores nothing; PHPStan reports it.
					// phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( is_string( $pattern ) && $pattern !== '' && @preg_match( $pattern, $signature ) === 1 ) {
						return $identifier;
					}
				}

				foreach ( $texts as $text ) {
					if ( is_string( $text ) && strpos( $text, $signature ) !== false ) {
						return $identifier;
					}
				}
			}
		}

		return null;
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
