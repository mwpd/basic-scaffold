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
 * Checks that every architecture rule is registered, listed and documented.
 *
 * The rule manifest, the rules that run, and the catalogue in
 * docs/architecture-rules.md describe the same set of rules. When they drift
 * apart, a failure links to nothing, or a rule exists that nobody can look up.
 *
 * The checks work on file contents, so their tests can feed them broken
 * examples.
 */
final class RuleRegistry {

	/**
	 * Find the rule classes a PHPStan configuration registers.
	 *
	 * @param string $neon Contents of the configuration.
	 * @return array<string> Fully qualified class names.
	 */
	public static function find_registered_rules( string $neon ): array {
		$registered = [];

		foreach ( preg_split( '/^\s*-\s*$/m', $neon ) ?: [] as $service ) {
			if ( strpos( $service, 'phpstan.rules.rule' ) !== false
				&& preg_match( '/^\s*class:\s*(\S+)/m', $service, $matches ) ) {
				$registered[] = $matches[1];
			}
		}

		return $registered;
	}

	/**
	 * Find the architecture identifiers a source file uses.
	 *
	 * @param string $source Source code.
	 * @return array{constants: array<string>, literals: array<string>} Names of
	 *         the RuleManifest constants it refers to, and identifiers it spells
	 *         out as strings instead.
	 */
	public static function find_identifiers( string $source ): array {
		preg_match_all( '/RuleManifest::([A-Z][A-Z0-9_]+)/', $source, $constants );
		preg_match_all( '/[\'"](architecture\.[A-Za-z0-9]+)[\'"]/', $source, $literals );

		return [
			'constants' => array_values( array_unique( $constants[1] ) ),
			'literals'  => array_values( array_unique( $literals[1] ) ),
		];
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
	 * @param array<string, string> $rule_classes Source of every PHPStan rule
	 *                                            class, keyed by class name.
	 * @param array<string>         $registered   Rule classes the
	 *                                            configuration registers.
	 * @param array<string, string> $sources      Source of every file that
	 *                                            reports architecture failures,
	 *                                            keyed by path.
	 * @param array<string, string> $manifest     Identifier of each manifest
	 *                                            constant, keyed by constant.
	 * @param array<string>         $documented   Identifiers the catalogue
	 *                                            documents.
	 * @return array<string> Problems found.
	 */
	public static function find_problems(
		array $rule_classes,
		array $registered,
		array $sources,
		array $manifest,
		array $documented
	): array {
		$problems = [];

		foreach ( array_keys( $rule_classes ) as $class_name ) {
			if ( ! in_array( $class_name, $registered, true ) ) {
				$problems[] = sprintf(
					'%s is a PHPStan rule, but tests/php/PHPStan/rules.neon does not register it, so it never runs.'
					. ' Add it under services with the phpstan.rules.rule tag.',
					$class_name
				);
			}
		}

		$used = [];
		foreach ( $sources as $path => $source ) {
			$identifiers = self::find_identifiers( $source );

			foreach ( $identifiers['constants'] as $constant ) {
				if ( isset( $manifest[ $constant ] ) ) {
					$used[] = $manifest[ $constant ];
				}
			}

			foreach ( $identifiers['literals'] as $literal ) {
				$problems[] = sprintf(
					'%1$s reports %2$s by spelling it out. Add a constant for it to RuleManifest, with its'
					. ' principle and catalogue entry, and use RuleManifest::get_explanation() for the tip.',
					$path,
					$literal
				);
			}
		}

		foreach ( $manifest as $identifier ) {
			if ( ! in_array( $identifier, $used, true ) ) {
				$problems[] = sprintf(
					'RuleManifest lists %s, but no rule or check reports it. Remove the entry and its catalogue'
					. ' section, or register the rule that reports it.',
					$identifier
				);
			}
		}

		foreach ( $documented as $identifier ) {
			if ( ! in_array( $identifier, $manifest, true ) ) {
				$problems[] = sprintf(
					'docs/architecture-rules.md documents %s, which RuleManifest does not list. Remove the section,'
					. ' or add the rule to RuleManifest.',
					$identifier
				);
			}
		}

		return $problems;
	}
}
