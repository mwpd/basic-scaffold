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

use ReflectionClass;

/**
 * The list of architecture rules.
 *
 * Every architecture rule, whether it is a PHPStan rule or a check in the
 * architecture test suite, has exactly one entry here. The entry ties the
 * rule's identifier to the design principle it enforces, to the configuration
 * that tunes it, and to its section in the rule catalogue. A rule that
 * enforces one of the hard rules in AGENTS.md rather than a design principle
 * names that document instead.
 *
 * Failure messages take their "why" from this list, so a rule never has to
 * repeat where its principle is documented.
 *
 * @phpstan-type RuleEntry array{
 *     principle: string,
 *     principle_anchor: string,
 *     configuration: string,
 *     document?: string,
 * }
 */
final class RuleManifest {

	/**
	 * Document that holds the design principles, relative to the plugin root.
	 */
	public const DESIGN_PRINCIPLES = 'docs/design-principles.md';

	/**
	 * Document that holds the rule catalogue, relative to the plugin root.
	 */
	public const CATALOGUE = 'docs/architecture-rules.md';

	/**
	 * Document that holds the hard rules, relative to the plugin root.
	 */
	public const HARD_RULES = 'AGENTS.md';

	public const FINAL_BY_DEFAULT = 'architecture.finalByDefault';

	public const NO_STATIC_STATE = 'architecture.noStaticState';

	public const NO_WORDPRESS_ERROR_MECHANISMS = 'architecture.noWordPressErrorMechanisms';

	public const THROW_ONLY_PLUGIN_EXCEPTIONS = 'architecture.throwOnlyPluginExceptions';

	public const EXCEPTION_SHAPE = 'architecture.exceptionShape';

	public const EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS = 'architecture.exceptionsThroughNamedConstructors';

	public const DEPENDENCY_DIRECTION = 'architecture.dependencyDirection';

	public const NEW_ONLY_WHERE_ALLOWED = 'architecture.newOnlyWhereAllowed';

	public const NO_HOOKS_IN_CONSTRUCTOR = 'architecture.noHooksInConstructor';

	public const HOOKS_IN_LIFECYCLE_METHODS = 'architecture.hooksInLifecycleMethods';

	public const NO_CONTEXT_CHECKS_IN_REGISTER = 'architecture.noContextChecksInRegister';

	public const PREFIXED_NAMES = 'architecture.prefixedNames';

	public const STRICT_TYPES = 'architecture.strictTypes';

	public const DECLARATIONS_FOLLOW_PSR4 = 'architecture.declarationsFollowPsr4';

	public const NO_SIDE_EFFECTS_ON_INCLUDE = 'architecture.noSideEffectsOnInclude';

	public const NO_RUNTIME_DEPENDENCIES = 'architecture.noRuntimeDependencies';

	public const TESTS_ARE_DISCOVERED = 'architecture.testsAreDiscovered';

	public const NO_SUPPRESSION = 'architecture.noSuppression';

	/**
	 * All rules, keyed by their identifier.
	 *
	 * @var array<string, RuleEntry>
	 */
	private const RULES = [
		self::FINAL_BY_DEFAULT                      => [
			'principle'        => 'Principle 6: Final by default',
			'principle_anchor' => '6-final-by-default',
			'configuration'    => 'parameters.architecture.finalByDefault',
		],
		self::NO_STATIC_STATE                       => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'configuration'    => 'parameters.architecture.noStaticState',
		],
		self::NO_WORDPRESS_ERROR_MECHANISMS         => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'configuration'    => 'parameters.architecture.noWordPressErrorMechanisms',
		],
		self::THROW_ONLY_PLUGIN_EXCEPTIONS          => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'configuration'    => 'parameters.architecture.exceptions',
		],
		self::EXCEPTION_SHAPE                       => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'configuration'    => 'parameters.architecture.exceptions',
		],
		self::EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'configuration'    => 'parameters.architecture.exceptions',
		],
		self::DEPENDENCY_DIRECTION                  => [
			'principle'        => 'Principle 4: SOLID at the top, WordPress at the edges',
			'principle_anchor' => '4-solid-at-the-top-wordpress-at-the-edges',
			'configuration'    => 'parameters.architecture.dependencyDirection',
		],
		self::NEW_ONLY_WHERE_ALLOWED                => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'configuration'    => 'parameters.architecture.newOnlyWhereAllowed',
		],
		self::NO_HOOKS_IN_CONSTRUCTOR               => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'configuration'    => 'parameters.architecture.namespace',
		],
		self::HOOKS_IN_LIFECYCLE_METHODS            => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'configuration'    => 'parameters.architecture.hooks.lifecycleMethods',
		],
		self::NO_CONTEXT_CHECKS_IN_REGISTER         => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'configuration'    => 'parameters.architecture.hooks.contextFunctions',
		],
		self::PREFIXED_NAMES                        => [
			'principle'        => 'Principle 13: One plugin, to arbitrary complexity',
			'principle_anchor' => '13-one-plugin-to-arbitrary-complexity',
			'configuration'    => 'parameters.architecture.prefixedNames',
		],
		self::STRICT_TYPES                          => [
			'principle'        => 'Hard rule: every PHP class file declares strict_types=1',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'RepositoryLayout',
			'document'         => self::HARD_RULES,
		],
		self::DECLARATIONS_FOLLOW_PSR4              => [
			'principle'        => 'Principle 1: Runs on PHP and WordPress alone',
			'principle_anchor' => '1-runs-on-php-and-wordpress-alone',
			'configuration'    => 'RepositoryLayout',
		],
		self::NO_SIDE_EFFECTS_ON_INCLUDE            => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'configuration'    => 'RepositoryLayout',
		],
		self::NO_RUNTIME_DEPENDENCIES               => [
			'principle'        => 'Principle 1: Runs on PHP and WordPress alone',
			'principle_anchor' => '1-runs-on-php-and-wordpress-alone',
			'configuration'    => 'composer-dependency-analyser.php',
		],
		self::TESTS_ARE_DISCOVERED                  => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'RepositoryLayout',
			'document'         => self::HARD_RULES,
		],
		self::NO_SUPPRESSION                        => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'phpstan.neon.dist',
			'document'         => self::HARD_RULES,
		],
	];

	/**
	 * Get the identifier of every rule, keyed by the name of its constant.
	 *
	 * @return array<string, string> Identifiers keyed by constant name.
	 */
	public static function get_constants(): array {
		$constants = [];

		foreach ( ( new ReflectionClass( self::class ) )->getConstants() as $name => $value ) {
			if ( is_string( $value ) && isset( self::RULES[ $value ] ) ) {
				$constants[ $name ] = $value;
			}
		}

		return $constants;
	}

	/**
	 * Get all rules, keyed by their identifier.
	 *
	 * @return array<string, RuleEntry> Rules keyed by their identifier.
	 */
	public static function get_rules(): array {
		return self::RULES;
	}

	/**
	 * Get the anchor of a rule's section in the catalogue.
	 *
	 * This is the anchor GitHub generates for a heading that consists of the
	 * identifier in backticks.
	 *
	 * @param string $identifier Identifier of the rule.
	 * @return string Anchor of the rule's section, without the leading "#".
	 */
	public static function get_catalogue_anchor( string $identifier ): string {
		return strtolower( str_replace( '.', '', $identifier ) );
	}

	/**
	 * Get the document that holds the principle a rule enforces.
	 *
	 * @param string $identifier Identifier of the rule.
	 * @return string Path of the document, relative to the plugin root.
	 */
	public static function get_principle_document( string $identifier ): string {
		return self::RULES[ $identifier ]['document'] ?? self::DESIGN_PRINCIPLES;
	}

	/**
	 * Get the explanation that closes every failure message of a rule.
	 *
	 * It names the principle and links to it and to the rule's catalogue
	 * entry, so a reader can act on the failure without searching.
	 *
	 * @param string $identifier Identifier of the rule.
	 * @return string Explanation of the rule.
	 */
	public static function get_explanation( string $identifier ): string {
		$rule = self::RULES[ $identifier ];

		return sprintf(
			'%1$s, see %2$s#%3$s. Rule: %4$s#%5$s',
			$rule['principle'],
			self::get_principle_document( $identifier ),
			$rule['principle_anchor'],
			self::CATALOGUE,
			self::get_catalogue_anchor( $identifier )
		);
	}
}
