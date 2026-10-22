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
 * Checks that every architecture rule is registered, listed and documented.
 *
 * The rule manifest, the rules that run, and the catalogue in
 * docs/architecture-rules.md describe the same set of rules. When they drift
 * apart, a failure links to nothing, or a rule exists that nobody can look up.
 *
 * The checks work on decoded configuration and file contents, so their
 * tests can feed them broken examples.
 *
 * @phpstan-import-type RuleEntry from RuleManifest
 */
final class RuleRegistry {

	/**
	 * Find the rule classes a PHPStan configuration registers.
	 *
	 * @param string $neon Contents of the configuration.
	 * @return array<string> Fully qualified class names.
	 */
	public static function find_registered_rules( string $neon ): array {
		$config     = Neon::decode( $neon );
		$services   = is_array( $config ) && is_array( $config['services'] ?? null ) ? $config['services'] : [];
		$registered = [];

		foreach ( $services as $service ) {
			if ( is_array( $service )
				&& is_string( $service['class'] ?? null )
				&& in_array( 'phpstan.rules.rule', (array) ( $service['tags'] ?? [] ), true ) ) {
				$registered[] = $service['class'];
			}
		}

		return $registered;
	}

	/**
	 * Find the rules the catalogue documents.
	 *
	 * @param string $catalogue Contents of the catalogue.
	 * @return array<string> Identifiers with a section of their own.
	 */
	public static function find_documented_rules( string $catalogue ): array {
		preg_match_all( '/^###\s+`(architecture\.[A-Za-z0-9]+)`\s*$/m', $catalogue, $matches );

		return $matches[1];
	}

	/**
	 * Find the problems between the rules, the manifest and the catalogue.
	 *
	 * @param array<string, RuleEntry> $manifest     Manifest entries, keyed by
	 *                                               identifier.
	 * @param array<string, string>    $constants    Every constant that holds
	 *                                               an identifier, keyed by name.
	 * @param array<string>            $rule_classes Every PHPStan rule class.
	 * @param array<string>            $registered   Rule classes the
	 *                                               configuration registers.
	 * @param array<string, string>    $sources      Source of every file that
	 *                                               reports architecture
	 *                                               failures, keyed by path.
	 * @param array<string>            $documented   Identifiers the catalogue
	 *                                               documents.
	 * @return array<string> Problems found.
	 */
	public static function find_problems(
		array $manifest,
		array $constants,
		array $rule_classes,
		array $registered,
		array $sources,
		array $documented
	): array {
		$problems = [];
		$checkers = [];

		foreach ( $constants as $constant => $identifier ) {
			if ( ! isset( $manifest[ $identifier ] ) ) {
				$problems[] = sprintf(
					'RuleManifest::%1$s holds %2$s, which has no entry in RuleManifest. Add the entry, with its'
					. ' principle, what checks it and its catalogue section.',
					$constant,
					$identifier
				);
			}
		}

		foreach ( $manifest as $identifier => $rule ) {
			foreach ( $rule['checked_by'] as $checker ) {
				$checkers[] = $checker;
				$problem    = self::check_checker( $identifier, $checker, $registered );

				if ( $problem !== null ) {
					$problems[] = $problem;
				}
			}

			if ( $rule['checked_by'] === [] ) {
				$problems[] = sprintf(
					'RuleManifest lists %s, but nothing checks it. Name the PHPStan rule or the architecture test'
					. ' that does in checked_by, or remove the entry and its catalogue section.',
					$identifier
				);
			}

			foreach ( $rule['signatures'] as $signature ) {
				// Messages are mostly single-quoted strings, so undo their escapes.
				if ( strpos( str_replace( "\\'", "'", implode( "\n", $sources ) ), $signature ) === false ) {
					$problems[] = sprintf(
						'RuleManifest lists "%1$s" as part of the messages of %2$s, but no rule reports it. Keep the'
						. ' signatures in step with the messages, so ignores that match them are recognized.',
						$signature,
						$identifier
					);
				}
			}
		}

		foreach ( array_unique( array_merge( $rule_classes, $registered ) ) as $class_name ) {
			if ( ! in_array( $class_name, $registered, true ) ) {
				$problems[] = sprintf(
					'%s is a PHPStan rule, but tests/php/PHPStan/rules.neon does not register it, so it never'
					. ' runs. Add it under services with the phpstan.rules.rule tag.',
					$class_name
				);
			} elseif ( ! in_array( $class_name, $checkers, true ) ) {
				$problems[] = sprintf(
					'%s runs, but no RuleManifest entry names it in checked_by, so its failures link to no'
					. ' principle. Add it to the entry of the rule it reports.',
					$class_name
				);
			}
		}

		foreach ( $sources as $path => $source ) {
			preg_match_all( '/[\'"](architecture\.[A-Za-z0-9]+)[\'"]/', $source, $literals );

			foreach ( array_unique( $literals[1] ) as $literal ) {
				$problems[] = sprintf(
					'%1$s reports %2$s by spelling it out. Use its RuleManifest constant, and'
					. ' RuleManifest::get_explanation() for the explanation.',
					$path,
					$literal
				);
			}
		}

		foreach ( $documented as $identifier ) {
			if ( ! isset( $manifest[ $identifier ] ) ) {
				$problems[] = sprintf(
					'docs/architecture-rules.md documents %s, which RuleManifest does not list. Remove the'
					. ' section, or add the rule to RuleManifest.',
					$identifier
				);
			}
		}

		return $problems;
	}

	/**
	 * Check one entry of a rule's checked_by.
	 *
	 * @param string        $identifier Identifier of the rule.
	 * @param string        $checker    PHPStan rule class, or test method as
	 *                                  `Class::method`.
	 * @param array<string> $registered Rule classes the configuration
	 *                                  registers.
	 * @return string|null What is wrong, or null.
	 */
	private static function check_checker( string $identifier, string $checker, array $registered ): ?string {
		if ( strpos( $checker, '::' ) !== false ) {
			[ $class_name, $method ] = explode( '::', $checker, 2 );

			return method_exists( $class_name, $method ) && strpos( $method, 'test' ) === 0
				? null
				: sprintf(
					'%1$s is checked by %2$s, which is not a test method. Point checked_by at the test that runs'
					. ' the check on the plugin.',
					$identifier,
					$checker
				);
		}

		return in_array( $checker, $registered, true )
			? null
			: sprintf(
				'%1$s is checked by %2$s, which tests/php/PHPStan/rules.neon does not register. Register the rule,'
				. ' or correct checked_by.',
				$identifier,
				$checker
			);
	}
}
