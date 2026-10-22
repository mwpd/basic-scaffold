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
 * The list of architecture rules.
 *
 * Every architecture rule, whether it is a PHPStan rule or a check in the
 * architecture test suite, has exactly one entry here. The entry ties the
 * rule's identifier to the design principle it enforces, to the configuration
 * that tunes it, and to its section in the rule catalogue.
 *
 * Failure messages take their "why" from this list, so a rule never has to
 * repeat where its principle is documented.
 *
 * @phpstan-type RuleEntry array{
 *     principle: string,
 *     principle_anchor: string,
 *     configuration: string,
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

	public const FINAL_BY_DEFAULT = 'architecture.finalByDefault';

	public const NO_STATIC_STATE = 'architecture.noStaticState';

	public const NO_WORDPRESS_ERROR_MECHANISMS = 'architecture.noWordPressErrorMechanisms';

	public const THROW_ONLY_PLUGIN_EXCEPTIONS = 'architecture.throwOnlyPluginExceptions';

	public const EXCEPTION_SHAPE = 'architecture.exceptionShape';

	public const EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS = 'architecture.exceptionsThroughNamedConstructors';

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
	];

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
			self::DESIGN_PRINCIPLES,
			$rule['principle_anchor'],
			self::CATALOGUE,
			self::get_catalogue_anchor( $identifier )
		);
	}
}
