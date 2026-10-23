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
		/**
		 * Neon::decode() declares a native mixed return type, which Psalm
		 * reads as a reserved word when it targets PHP 7.4.
		 *
		 * @psalm-suppress ReservedWord
		 */
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
	 * Find the rules each pattern lists, keyed by the pattern's anchor.
	 *
	 * A pattern is a "## " section of docs/patterns.md. The rules it lists are
	 * the identifiers in backticks in its paragraph that starts with
	 * "**Rules:**".
	 *
	 * @param string $patterns Contents of the patterns document.
	 * @return array<string, array<string>> Identifiers, keyed by pattern anchor.
	 */
	public static function find_pattern_rules( string $patterns ): array {
		$found = [];

		foreach ( self::split_sections( $patterns, '##' ) as $anchor => $section ) {
			$found[ $anchor ] = [];

			if ( preg_match( '/^\*\*Rules:\*\*(.*?)(?:\n\s*\n|\z)/ms', $section, $rules ) === 1 ) {
				preg_match_all( '/`(architecture\.[A-Za-z0-9]+)`/', $rules[1], $matches );
				$found[ $anchor ] = array_values( array_unique( $matches[1] ) );
			}
		}

		return $found;
	}

	/**
	 * Find the patterns each rule's catalogue section links to.
	 *
	 * @param string $catalogue Contents of the catalogue.
	 * @return array<string, array<string>> Pattern anchors, keyed by identifier.
	 */
	public static function find_linked_patterns( string $catalogue ): array {
		$found = [];

		foreach ( self::split_sections( $catalogue, '###' ) as $section ) {
			if ( preg_match( '/\A`(architecture\.[A-Za-z0-9]+)`/', $section, $heading ) !== 1 ) {
				continue;
			}

			preg_match_all( '/\(patterns\.md#([a-z0-9_-]+)\)/', $section, $matches );
			$found[ $heading[1] ] = array_values( array_unique( $matches[1] ) );
		}

		return $found;
	}

	/**
	 * Find where the rules and the patterns that cite them disagree.
	 *
	 * A pattern lists the rules that enforce it, and a rule names the pattern
	 * its failures link to. Both directions have to hold, so a failure never
	 * links to a pattern that does not mention the rule, and a pattern never
	 * claims a rule that does not exist or does not lead back to it.
	 *
	 * @param array<string, RuleEntry>     $manifest Manifest entries, keyed by
	 *                                               identifier.
	 * @param array<string, array<string>> $patterns Rules each pattern lists,
	 *                                               keyed by pattern anchor.
	 * @param array<string, array<string>> $linked   Patterns each rule's
	 *                                               catalogue section links to,
	 *                                               keyed by identifier.
	 * @return array<string> Problems found.
	 */
	public static function find_pattern_problems( array $manifest, array $patterns, array $linked ): array {
		$problems = [];

		foreach ( $patterns as $anchor => $identifiers ) {
			foreach ( $identifiers as $identifier ) {
				if ( ! isset( $manifest[ $identifier ] ) ) {
					$problems[] = sprintf(
						'%1$s#%2$s lists %3$s under Rules, which RuleManifest does not list. Name a rule that'
						. ' exists, or describe a rule still being built by its issue instead of its identifier.',
						RuleManifest::PATTERNS,
						$anchor,
						$identifier
					);
					continue;
				}

				$own_pattern = $manifest[ $identifier ]['pattern'] ?? null;
				if ( $own_pattern !== $anchor && ! in_array( $anchor, $linked[ $identifier ] ?? [], true ) ) {
					$problems[] = sprintf(
						'%1$s#%2$s lists %3$s under Rules, but the rule does not lead back to it. Link the pattern'
						. ' from the rule\'s section in %4$s, or make it the rule\'s pattern in RuleManifest.',
						RuleManifest::PATTERNS,
						$anchor,
						$identifier,
						RuleManifest::CATALOGUE
					);
				}
			}
		}

		foreach ( $manifest as $identifier => $rule ) {
			$pattern = $rule['pattern'] ?? null;
			if ( $pattern === null ) {
				continue;
			}

			if ( ! isset( $patterns[ $pattern ] ) ) {
				$problems[] = sprintf(
					'%1$s links its failures to %2$s#%3$s, which is not a pattern. Point the rule\'s pattern in'
					. ' RuleManifest at a "## " section of %2$s.',
					$identifier,
					RuleManifest::PATTERNS,
					$pattern
				);
				continue;
			}

			if ( ! in_array( $identifier, $patterns[ $pattern ], true ) ) {
				$problems[] = sprintf(
					'%1$s links its failures to %2$s#%3$s, but that pattern does not list it under Rules. Add'
					. ' the rule to the pattern\'s Rules, or point the rule\'s pattern in RuleManifest at the'
					. ' pattern it enforces.',
					$identifier,
					RuleManifest::PATTERNS,
					$pattern
				);
			}
		}

		return $problems;
	}

	/**
	 * Split a Markdown document into the sections under one level of heading.
	 *
	 * @param string $markdown Contents of the document.
	 * @param string $level    The heading's hashes, such as "##".
	 * @return array<string, string> Section text after the heading's hashes,
	 *                               keyed by the anchor GitHub generates for
	 *                               the heading.
	 */
	private static function split_sections( string $markdown, string $level ): array {
		$sections = [];
		$parts    = preg_split( '/^' . $level . '\s+/m', self::strip_code_blocks( $markdown ) );

		foreach ( array_slice( (array) $parts, 1 ) as $part ) {
			$part    = (string) $part;
			$heading = (string) strtok( $part, "\n" );

			$sections[ self::get_anchor( $heading ) ] = $part;
		}

		return $sections;
	}

	/**
	 * Remove fenced code blocks from a Markdown document.
	 *
	 * A line inside a fence that looks like a heading or a Rules paragraph is
	 * an example, not part of the document's structure.
	 *
	 * @param string $markdown Contents of the document.
	 * @return string The document without its fenced code blocks.
	 */
	public static function strip_code_blocks( string $markdown ): string {
		return (string) preg_replace( '/^(`{3,}|~{3,})[^\n]*\n.*?^\1[ \t]*$/ms', '', $markdown );
	}

	/**
	 * Get the anchor GitHub generates for a heading.
	 *
	 * @param string $heading Text of the heading.
	 * @return string Anchor, without the leading "#".
	 */
	public static function get_anchor( string $heading ): string {
		$anchor = strtolower( trim( $heading ) );
		$anchor = (string) preg_replace( '/[^a-z0-9 _-]/', '', $anchor );

		return str_replace( ' ', '-', $anchor );
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
			$parts      = explode( '::', $checker, 2 );
			$class_name = $parts[0];
			$method     = $parts[1] ?? '';

			return class_exists( $class_name )
				&& method_exists( $class_name, $method )
				&& strpos( $method, 'test' ) === 0
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
