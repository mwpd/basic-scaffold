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
 * repeat where its principle is documented. An entry may also name the
 * pattern in docs/patterns.md that meets the need behind the code a rule
 * rejects, so a failure also says how.
 *
 * Each entry also names what checks the rule, so a test can prove that it
 * runs: PHPStan rule classes, or the test methods of the architecture suite.
 * Rule classes are named by string, not by ::class: Psalm scans every class
 * this file references, and the rules are built on PHPStan 2, which Psalm 5
 * cannot load. RuleRegistrationTest still catches a misspelled name, because
 * it reports any rule that no entry names.
 *
 * A PHPStan rule's entry lists fixed phrases of its messages, so an ignore
 * that matches them can be recognized.
 *
 * @phpstan-type RuleEntry array{
 *     principle: string,
 *     principle_anchor: string,
 *     pattern?: string,
 *     configuration: string,
 *     document?: string,
 *     checked_by: array<string>,
 *     signatures: array<string>,
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
	 * Document that holds the patterns, relative to the plugin root.
	 */
	public const PATTERNS = 'docs/patterns.md';

	/**
	 * Document that holds the hard rules, relative to the plugin root.
	 */
	public const HARD_RULES = 'AGENTS.md';

	public const FINAL_BY_DEFAULT = 'architecture.finalByDefault';

	public const NAMES_WITHOUT_TYPE_KIND = 'architecture.namesWithoutTypeKind';

	public const NO_STATIC_STATE = 'architecture.noStaticState';

	public const PLUGIN_FACTORY_ONLY_AT_EDGES = 'architecture.pluginFactoryOnlyAtEdges';

	public const NO_GLOBAL_STATE = 'architecture.noGlobalState';

	public const NO_WORDPRESS_ERROR_MECHANISMS = 'architecture.noWordPressErrorMechanisms';

	public const NO_FALSE_ON_FAILURE = 'architecture.noFalseOnFailure';

	public const THROW_ONLY_PLUGIN_EXCEPTIONS = 'architecture.throwOnlyPluginExceptions';

	public const EXCEPTION_SHAPE = 'architecture.exceptionShape';

	public const EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS = 'architecture.exceptionsThroughNamedConstructors';

	public const EXCEPTIONS_KEEP_CAUSE = 'architecture.exceptionsKeepCause';

	public const RAW_IS_ESCAPED = 'architecture.rawIsEscaped';

	public const OUTPUT_ONLY_THROUGH_VIEWS = 'architecture.outputOnlyThroughViews';

	public const DEPENDENCY_DIRECTION = 'architecture.dependencyDirection';
	public const NO_SERVICE_LOCATOR   = 'architecture.noServiceLocator';

	public const NEW_ONLY_WHERE_ALLOWED = 'architecture.newOnlyWhereAllowed';

	public const CONSTRUCTORS_ONLY_ASSIGN = 'architecture.constructorsOnlyAssign';

	public const NO_HOOKS_IN_CONSTRUCTOR = 'architecture.noHooksInConstructor';

	public const HOOKS_IN_LIFECYCLE_METHODS = 'architecture.hooksInLifecycleMethods';

	public const NO_CONTEXT_CHECKS_IN_REGISTER = 'architecture.noContextChecksInRegister';

	public const QUERY_CONTEXT_NEEDS_DELAY = 'architecture.queryContextNeedsDelay';

	public const REGISTRATION_ACTION_KNOWN = 'architecture.registrationActionKnown';

	public const PREFIXED_NAMES = 'architecture.prefixedNames';

	public const STRICT_TYPES = 'architecture.strictTypes';

	public const EXPLICIT_NULLABLE_PARAMETERS = 'architecture.explicitNullableParameters';

	public const DECLARATIONS_FOLLOW_PSR4 = 'architecture.declarationsFollowPsr4';

	public const NO_SIDE_EFFECTS_ON_INCLUDE = 'architecture.noSideEffectsOnInclude';

	public const BOOTSTRAP_CONTRACT = 'architecture.bootstrapContract';

	public const NO_RUNTIME_DEPENDENCIES = 'architecture.noRuntimeDependencies';

	public const TESTS_ARE_DISCOVERED = 'architecture.testsAreDiscovered';

	public const SAMPLES_ARE_TESTED = 'architecture.samplesAreTested';

	public const NO_SUPPRESSION = 'architecture.noSuppression';

	public const SUPPRESSIONS_ARE_NAMED = 'architecture.suppressionsAreNamed';

	public const GATES_AT_STRENGTH = 'architecture.gatesAtStrength';

	public const CAPABILITIES_ON_SERVICES = 'architecture.capabilitiesOnServices';

	public const SERVICES_DECLARED = 'architecture.servicesDeclared';

	public const COMPOSITION_ROOT_CONTRACTS = 'architecture.compositionRootContracts';

	public const CITATIONS_RESOLVE = 'architecture.citationsResolve';

	public const INFRASTRUCTURE_API_SNAPSHOT = 'architecture.infrastructureApiSnapshot';

	/**
	 * All rules, keyed by their identifier.
	 *
	 * @var array<string, RuleEntry>
	 */
	private const RULES = [
		self::INFRASTRUCTURE_API_SNAPSHOT           => [
			'principle'        => 'Hard rule: infrastructure changes are API changes',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'parameters.architecture.namespace and '
				. 'parameters.architecture.dependencyDirection.infrastructure',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				InfrastructureApiSnapshotTest::class . '::test_the_infrastructure_api_matches_the_snapshot',
			],
			'signatures'       => [],
		],
		self::CITATIONS_RESOLVE                     => [
			'principle'        => 'Principle 14: The code teaches',
			'principle_anchor' => '14-the-code-teaches',
			'configuration'    => 'RepositoryLayout::DOCUMENTATION',
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_documentation_citations_resolve',
			],
			'signatures'       => [],
		],
		self::FINAL_BY_DEFAULT                      => [
			'principle'        => 'Principle 6: Final by default',
			'principle_anchor' => '6-final-by-default',
			'pattern'          => 'i-need-to-start-simple-and-add-implementations-later',
			'configuration'    => 'parameters.architecture.finalByDefault',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\FinalByDefaultRule',
			],
			'signatures'       => [
				'is not final. Declare it final',
			],
		],
		self::NAMES_WITHOUT_TYPE_KIND               => [
			'principle'        => 'Principle 8: Types name concepts, not just data',
			'principle_anchor' => '8-types-name-concepts-not-just-data',
			'pattern'          => 'i-need-to-start-simple-and-add-implementations-later',
			'configuration'    => 'parameters.architecture.namesWithoutTypeKind',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NamesWithoutTypeKindRule',
			],
			'signatures'       => [
				'encodes its kind in its name',
			],
		],
		self::NO_STATIC_STATE                       => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-a-singleton',
			'configuration'    => 'parameters.architecture.noStaticState',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoStaticStateRule',
			],
			'signatures'       => [
				'keeps state outside any object',
			],
		],
		self::PLUGIN_FACTORY_ONLY_AT_EDGES          => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-to-reach-the-plugin-from-a-static-wordpress-callback',
			'configuration'    => 'parameters.architecture.pluginFactoryOnlyAtEdges',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\PluginFactoryOnlyAtEdgesRule',
			],
			'signatures'       => [
				'uses the plugin factory as a service locator',
			],
		],
		self::NO_GLOBAL_STATE                       => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-wordpress-state-such-as-the-current-post',
			'configuration'    => 'parameters.architecture.noGlobalState',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoGlobalStateRule',
			],
			'signatures'       => [
				'pulls state from globals',
				'accesses global state',
				'bypasses an input adapter',
			],
		],
		self::NO_WORDPRESS_ERROR_MECHANISMS         => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.noWordPressErrorMechanisms',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoErrorReportingFunctionsRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorCreationRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorInMethodsRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorInFunctionsRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorInClosuresRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorInArrowFunctionsRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoWordPressErrorInPropertiesRule',
			],
			'signatures'       => [
				'reports an error where it happens',
				'carries an error through the plugin',
				'takes or returns a WP_Error',
				'holds a WP_Error',
			],
		],
		self::NO_FALSE_ON_FAILURE                   => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.namespace',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoFalseOnFailureRule',
			],
			'signatures'       => [
				'declares a return type that unions false with another type',
			],
		],
		self::THROW_ONLY_PLUGIN_EXCEPTIONS          => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.exceptions',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\ThrowOnlyPluginExceptionsRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\CreateOnlyPluginExceptionsRule',
			],
			'signatures'       => [
				'which does not implement',
				'creates an exception that does not implement',
			],
		],
		self::EXCEPTION_SHAPE                       => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.exceptions',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\ExceptionShapeRule',
			],
			'signatures'       => [
				'has no named constructor',
				'does not extend an SPL exception',
				'Only the plugin\'s exceptions live there',
				'where every exception of the plugin lives',
			],
		],
		self::EXCEPTIONS_THROUGH_NAMED_CONSTRUCTORS => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.exceptions',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\ExceptionsThroughNamedConstructorsRule',
			],
			'signatures'       => [
				'creates an exception outside its named constructors',
			],
		],
		self::EXCEPTIONS_KEEP_CAUSE                 => [
			'principle'        => 'Principle 10: Errors are exceptions',
			'principle_anchor' => '10-errors-are-exceptions',
			'pattern'          => 'i-need-to-report-a-failure',
			'configuration'    => 'parameters.architecture.exceptionsKeepCause',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\ExceptionsKeepCauseRule',
			],
			'signatures'       => [
				'loses the caught exception',
				'discards the caught exception',
			],
		],
		self::OUTPUT_ONLY_THROUGH_VIEWS             => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-print-output',
			'configuration'    => 'parameters.architecture.outputOnlyThroughViews',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\OutputOnlyThroughViewsRule',
			],
			'signatures'       => [
				'does not come directly from a Renderable or View method call',
			],
		],
		self::RAW_IS_ESCAPED                        => [
			'principle'        => 'Principle 9: Never trust what WordPress hands over',
			'principle_anchor' => '9-never-trust-what-wordpress-hands-over',
			'pattern'          => 'i-need-to-print-output',
			'configuration'    => 'parameters.architecture.rawIsEscaped',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\RawIsEscapedRule',
			],
			'signatures'       => [
				'prints an unescaped raw() value',
			],
		],
		self::DEPENDENCY_DIRECTION                  => [
			'principle'        => 'Principle 4: SOLID at the top, WordPress at the edges',
			'principle_anchor' => '4-solid-at-the-top-wordpress-at-the-edges',
			'pattern'          => 'i-need-to-create-objects-on-demand',
			'configuration'    => 'parameters.architecture.dependencyDirection',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\DependencyDirectionRule',
			],
			'signatures'       => [
				'Production code never depends on the tests',
				'Generated plugins build on the infrastructure',
				'Exceptions only depend on each other',
				'and bind the implementation in the composition root',
				'knows how the plugin is put together',
			],
		],
		self::NO_SERVICE_LOCATOR                    => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-a-service-locator',
			'configuration'    => 'parameters.architecture.noServiceLocator',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoServiceLocatorRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoInjectorConfigurationRule',
			],
			'signatures'       => [
				'depends on the service container',
				'Configure bindings, sharing and delegations in the composition root',
			],
		],
		self::NEW_ONLY_WHERE_ALLOWED                => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-to-create-objects-on-demand',
			'configuration'    => 'parameters.architecture.newOnlyWhereAllowed',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NewOnlyWhereAllowedRule',
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\DynamicCreationRule',
			],
			'signatures'       => [
				'creates a collaborator',
				'new with a class chosen at runtime',
				'is not a nullable parameter typed to an interface',
			],
		],
		self::CONSTRUCTORS_ONLY_ASSIGN              => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-add-hooks',
			'configuration'    => 'parameters.architecture.constructorsOnlyAssign.allowed',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\ConstructorsOnlyAssignRule',
			],
			'signatures'       => [
				'does work beyond storing dependencies',
			],
		],
		self::NO_HOOKS_IN_CONSTRUCTOR               => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-add-hooks',
			'configuration'    => 'parameters.architecture.namespace',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoHooksInConstructorRule',
			],
			'signatures'       => [
				'hooks into WordPress while the object is being built',
			],
		],
		self::HOOKS_IN_LIFECYCLE_METHODS            => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-add-hooks',
			'configuration'    => 'parameters.architecture.hooks.lifecycleMethods',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\HooksInLifecycleMethodsRule',
			],
			'signatures'       => [
				'changes a hook outside a lifecycle method',
			],
		],
		self::NO_CONTEXT_CHECKS_IN_REGISTER         => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-a-service-only-in-some-requests',
			'configuration'    => 'parameters.architecture.hooks',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\NoContextChecksInRegisterRule',
			],
			'signatures'       => [
				'decides at registration whether the service applies',
				'waits for an action itself',
			],
		],
		self::QUERY_CONTEXT_NEEDS_DELAY             => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-hook-in-later',
			'configuration'    => 'parameters.architecture.queryContextNeedsDelay.queryFunctions',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\QueryContextNeedsDelayRule',
			],
			'signatures'       => [
				'asks about the query before it is available',
			],
		],
		self::REGISTRATION_ACTION_KNOWN             => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-hook-in-later',
			'configuration'    => 'RepositoryLayout::COMPOSITION_ROOT, RepositoryLayout::REGISTRATION_ACTIONS, '
				. 'RepositoryLayout::ADDITIONAL_CORE_ACTIONS, composition root HOOK_PREFIX',
			'checked_by'       => [
				CompositionRootContractTest::class . '::test_the_plugin_keeps_the_promises_of_its_composition_root',
				CompositionRootContractTest::class . '::test_it_reports_an_unknown_registration_action_with_the_fix',
			],
			'signatures'       => [ 'returns the unknown action' ],
		],
		self::PREFIXED_NAMES                        => [
			'principle'        => 'Principle 13: One plugin, to arbitrary complexity',
			'principle_anchor' => '13-one-plugin-to-arbitrary-complexity',
			'pattern'          => 'i-need-a-name-in-a-space-wordpress-shares',
			'configuration'    => 'parameters.architecture.prefixedNames',
			'checked_by'       => [
				'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\PrefixedNamesRule',
			],
			'signatures'       => [
				'without the plugin\'s prefix',
				'name the rule cannot check',
				'which exceeds the WordPress limit of',
			],
		],
		self::STRICT_TYPES                          => [
			'principle'        => 'Hard rule: every PHP class file declares strict_types=1',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'RepositoryLayout',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_plugin_declares_strict_types',
			],
			'signatures'       => [],
		],
		self::EXPLICIT_NULLABLE_PARAMETERS          => [
			'principle'        => 'Principle 1: Runs on PHP and WordPress alone',
			'principle_anchor' => '1-runs-on-php-and-wordpress-alone',
			'configuration'    => 'RepositoryLayout::PRODUCTION',
			'checked_by'       => [
				ExplicitNullableParametersTest::class . '::test_production_parameters_are_explicitly_nullable',
			],
			'signatures'       => [],
		],
		self::DECLARATIONS_FOLLOW_PSR4              => [
			'principle'        => 'Principle 1: Runs on PHP and WordPress alone',
			'principle_anchor' => '1-runs-on-php-and-wordpress-alone',
			'configuration'    => 'RepositoryLayout',
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_plugin_declares_one_class_per_file_named_after_its_path',
			],
			'signatures'       => [],
		],
		self::NO_SIDE_EFFECTS_ON_INCLUDE            => [
			'principle'        => 'Principle 7: Show the alternative to WordPress anti-patterns',
			'principle_anchor' => '7-show-the-alternative-to-wordpress-anti-patterns',
			'pattern'          => 'i-need-to-run-code-when-the-plugin-file-loads',
			'configuration'    => 'RepositoryLayout',
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_plugin_has_no_side_effects_on_include',
			],
			'signatures'       => [],
		],
		self::BOOTSTRAP_CONTRACT                    => [
			'principle'        => 'Principle 12: Extension points run at a hook, never at include time',
			'principle_anchor' => '12-extension-points-run-at-a-hook-never-at-include-time',
			'pattern'          => 'i-need-to-run-code-when-the-plugin-file-loads',
			'configuration'    => 'RepositoryLayout::BOOTSTRAP',
			'checked_by'       => [
				BootstrapContractTest::class . '::test_the_bootstrap_only_registers_the_entry_hooks',
			],
			'signatures'       => [],
		],
		self::NO_RUNTIME_DEPENDENCIES               => [
			'principle'        => 'Principle 1: Runs on PHP and WordPress alone',
			'principle_anchor' => '1-runs-on-php-and-wordpress-alone',
			'configuration'    => 'composer-dependency-analyser.php',
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_plugin_requires_nothing_but_php_at_runtime',
				RuntimeDependenciesTest::class . '::test_the_plugin_uses_no_development_package_at_runtime',
			],
			'signatures'       => [],
		],
		self::TESTS_ARE_DISCOVERED                  => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'RepositoryLayout',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				RepositoryStructureTest::class . '::test_the_plugin_puts_every_test_where_phpunit_finds_it',
			],
			'signatures'       => [],
		],
		self::SAMPLES_ARE_TESTED                    => [
			'principle'        => 'Principle 14: The code teaches',
			'principle_anchor' => '14-the-code-teaches',
			'configuration'    => 'RepositoryLayout and parameters.architecture',
			'checked_by'       => [
				SamplesAreTestedTest::class . '::test_the_plugin_tests_every_sample_class_and_view',
			],
			'signatures'       => [],
		],
		self::NO_SUPPRESSION                        => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'phpstan.neon.dist',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				SuppressionTest::class . '::test_the_plugin_does_not_silence_architecture_rules',
				RuleRegistrationTest::class . '::test_every_rule_is_registered_listed_and_documented',
			],
			'signatures'       => [],
		],
		self::SUPPRESSIONS_ARE_NAMED                => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'RepositoryLayout::RULE_FIXTURES',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				SuppressionTest::class . '::test_the_plugin_names_every_inline_suppression',
			],
			'signatures'       => [],
		],
		self::GATES_AT_STRENGTH                     => [
			'principle'        => 'Hard rule: do not weaken the gates',
			'principle_anchor' => 'hard-rules',
			'configuration'    => 'GatesAtStrengthChecks',
			'document'         => self::HARD_RULES,
			'checked_by'       => [
				GatesAtStrengthTest::class . '::test_the_plugin_keeps_its_quality_gates_at_strength',
			],
			'signatures'       => [],
		],
		self::CAPABILITIES_ON_SERVICES              => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-to-add-a-feature',
			'configuration'    => 'parameters.architecture.capabilitiesOnServices',
			'checked_by'       => [ 'MWPD\\BasicScaffold\\Tests\\PHPStan\\Rule\\CapabilitiesOnServicesRule' ],
			'signatures'       => [
				'implements a lifecycle capability without',
				'so delayed registration has work to run',
			],
		],
		self::SERVICES_DECLARED                     => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-to-add-a-feature',
			'configuration'    => 'RepositoryLayout::SERVICES_BUILT_ELSEWHERE',
			'checked_by'       => [
				ServicesDeclaredTest::class . '::test_the_plugin_declares_every_subsystem_service',
			],
			'signatures'       => [ 'with the route that builds it' ],
		],
		self::COMPOSITION_ROOT_CONTRACTS            => [
			'principle'        => 'Principle 5: One composition root builds the object graph',
			'principle_anchor' => '5-one-composition-root-builds-the-object-graph',
			'pattern'          => 'i-need-to-add-a-feature',
			'configuration'    => 'RepositoryLayout::COMPOSITION_ROOT, RepositoryLayout::REGISTRATION_ACTIONS',
			'checked_by'       => [
				CompositionRootContractTest::class . '::test_the_plugin_keeps_the_promises_of_its_composition_root',
				// Principle 11 extends this contract; it uses the same identifier and explanation.
				CompositionRootContractTest::class . '::test_it_reports_non_idempotent_lifecycle_steps',
				CompositionRootContractTest::class . '::test_it_accepts_guarded_and_setting_lifecycle_steps',
			],
			'signatures'       => [],
		],
	];

	/**
	 * Get every constant that holds a rule identifier, keyed by its name.
	 *
	 * That includes a constant whose rule has no entry yet, so a test can
	 * report it.
	 *
	 * @return array<string, string> Identifiers keyed by constant name.
	 */
	public static function get_constants(): array {
		$constants = [];

		foreach ( ( new ReflectionClass( self::class ) )->getConstants() as $name => $value ) {
			if ( is_string( $value ) && strpos( $value, 'architecture.' ) === 0 ) {
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
		return self::get_rules()[ $identifier ]['document'] ?? self::DESIGN_PRINCIPLES;
	}

	/**
	 * Get the explanation that closes every failure message of a rule.
	 *
	 * It names the principle and links to it, to the pattern that meets the
	 * need the code was after, when there is one, and to the rule's catalogue
	 * entry: why, how, and the exact check. A reader can act on the failure
	 * without searching.
	 *
	 * @param string $identifier Identifier of the rule.
	 * @return string Explanation of the rule.
	 */
	public static function get_explanation( string $identifier ): string {
		$rule    = self::RULES[ $identifier ];
		$pattern = isset( $rule['pattern'] )
			? sprintf( ' Pattern: %1$s#%2$s.', self::PATTERNS, $rule['pattern'] )
			: '';

		return sprintf(
			'%1$s, see %2$s#%3$s.%4$s Rule: %5$s#%6$s',
			$rule['principle'],
			self::get_principle_document( $identifier ),
			$rule['principle_anchor'],
			$pattern,
			self::CATALOGUE,
			self::get_catalogue_anchor( $identifier )
		);
	}
}
