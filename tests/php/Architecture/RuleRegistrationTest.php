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

use PHPStan\Rules\Rule;
use ReflectionClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Every architecture rule is registered, listed in the manifest and
 * documented in the catalogue.
 */
final class RuleRegistrationTest extends TestCase {

	public function test_every_rule_is_registered_listed_and_documented(): void {
		$root    = dirname( __DIR__, 3 );
		$rules   = [];
		$sources = [];
		$files   = array_merge(
			(array) glob( $root . '/tests/php/PHPStan/Rule/*.php' ),
			(array) glob( $root . '/tests/php/Architecture/*.php' )
		);

		foreach ( $files as $path ) {
			$path = (string) $path;
			if ( substr( $path, -8 ) === 'Test.php' || basename( $path ) === 'RuleManifest.php' ) {
				continue;
			}

			$sources[ substr( $path, strlen( $root ) + 1 ) ] = (string) file_get_contents( $path );

			foreach ( PhpFile::from_path( $path )->get_declarations() as $declaration ) {
				$name = $declaration['name'];
				if ( class_exists( $name ) && ( new ReflectionClass( $name ) )->implementsInterface( Rule::class ) ) {
					$rules[] = $name;
				}
			}
		}

		$neon     = (string) file_get_contents( $root . '/tests/php/PHPStan/rules.neon' );
		$problems = RuleRegistry::find_problems(
			RuleManifest::get_rules(),
			RuleManifest::get_constants(),
			$rules,
			RuleRegistry::find_registered_rules( $neon ),
			$sources,
			RuleRegistry::find_documented_rules( (string) file_get_contents( $root . '/' . RuleManifest::CATALOGUE ) )
		);

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_rules_that_are_not_registered_listed_or_documented(): void {
		$entry = static fn( array $checked_by, array $signatures = [] ): array => [
			'principle'        => 'Principle',
			'principle_anchor' => 'principle',
			'configuration'    => 'configuration',
			'checked_by'       => $checked_by,
			'signatures'       => $signatures,
		];

		$tested   = self::class . '::test_it_finds_the_rules_a_configuration_registers';
		$problems = RuleRegistry::find_problems(
			[
				'architecture.known'    => $entry( [ 'Acme\\KnownRule' ], [ 'is known' ] ),
				'architecture.unused'   => $entry( [] ),
				'architecture.tested'   => $entry( [ $tested ] ),
				'architecture.untested' => $entry( [ self::class . '::helper' ] ),
				'architecture.stale'    => $entry( [ 'Acme\\UnregisteredRule' ], [ 'no longer said' ] ),
			],
			[
				'KNOWN'   => 'architecture.known',
				'MISSING' => 'architecture.missing',
			],
			[ 'Acme\\KnownRule', 'Acme\\UnregisteredRule', 'Acme\\UnlistedRule' ],
			[ 'Acme\\KnownRule', 'Acme\\UnlistedRule' ],
			[
				'Known.php'   => "'This rule is known.'",
				'Spelled.php' => "->identifier( 'architecture.spelledOut' )",
			],
			[ 'architecture.known', 'architecture.removed' ]
		);

		$this->assertSame(
			[
				'RuleManifest::MISSING holds architecture.missing, which has no entry in RuleManifest.',
				'RuleManifest lists architecture.unused, but nothing checks it.',
				'architecture.untested is checked by ' . self::class . '::helper, which is not a test method.',
				'architecture.stale is checked by Acme\\UnregisteredRule, which tests/php/PHPStan/rules.neon does not'
					. ' register.',
				'RuleManifest lists "no longer said" as part of the messages of architecture.stale, but no rule'
					. ' reports it.',
				'Acme\\UnregisteredRule is a PHPStan rule, but tests/php/PHPStan/rules.neon does not register it, so'
					. ' it never runs.',
				'Acme\\UnlistedRule runs, but no RuleManifest entry names it in checked_by, so its failures link to'
					. ' no principle.',
				'Spelled.php reports architecture.spelledOut by spelling it out.',
				'docs/architecture-rules.md documents architecture.removed, which RuleManifest does not list.',
			],
			array_map( static fn( string $problem ): string => explode( '. ', $problem )[0] . '.', $problems )
		);
	}

	public function test_every_pattern_and_the_rules_it_lists_lead_to_each_other(): void {
		$root     = dirname( __DIR__, 3 );
		$problems = RuleRegistry::find_pattern_problems(
			RuleManifest::get_rules(),
			RuleRegistry::find_pattern_rules( (string) file_get_contents( $root . '/' . RuleManifest::PATTERNS ) ),
			RuleRegistry::find_linked_patterns( (string) file_get_contents( $root . '/' . RuleManifest::CATALOGUE ) )
		);

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_patterns_and_rules_that_do_not_lead_to_each_other(): void {
		$patterns  = implode(
			"\n",
			[
				'# Patterns',
				'',
				'## I need a thing',
				'',
				'**Rules:** `architecture.own`, `architecture.linked`, `architecture.unlinked`,',
				'`architecture.unknown`.',
				'',
				'Later text mentioning `architecture.ignored`.',
				'',
				'## I need another thing',
				'',
				'**Rules:** none yet.',
			]
		);
		$catalogue = implode(
			"\n",
			[
				'### `architecture.linked`',
				'',
				'**Pattern:** [I need a thing](patterns.md#i-need-a-thing).',
				'',
				'### `architecture.unlinked`',
				'',
				'No pattern.',
			]
		);
		$entry     = static fn( ?string $pattern ): array => $pattern === null
			? [
				'principle'        => 'Principle',
				'principle_anchor' => 'principle',
				'configuration'    => 'configuration',
				'checked_by'       => [],
				'signatures'       => [],
			]
			: [
				'principle'        => 'Principle',
				'principle_anchor' => 'principle',
				'pattern'          => $pattern,
				'configuration'    => 'configuration',
				'checked_by'       => [],
				'signatures'       => [],
			];

		$found = RuleRegistry::find_pattern_rules( $patterns );
		$this->assertSame(
			[
				'i-need-a-thing'       => [
					'architecture.own',
					'architecture.linked',
					'architecture.unlinked',
					'architecture.unknown',
				],
				'i-need-another-thing' => [],
			],
			$found
		);

		$problems = RuleRegistry::find_pattern_problems(
			[
				'architecture.own'      => $entry( 'i-need-a-thing' ),
				'architecture.linked'   => $entry( null ),
				'architecture.unlinked' => $entry( null ),
				'architecture.stray'    => $entry( 'i-need-another-thing' ),
			],
			$found,
			RuleRegistry::find_linked_patterns( $catalogue )
		);

		$this->assertSame(
			[
				'docs/patterns.md#i-need-a-thing lists architecture.unlinked under Rules, but the rule does not lead'
					. ' back to it.',
				'docs/patterns.md#i-need-a-thing lists architecture.unknown under Rules, which RuleManifest does not'
					. ' list.',
				'architecture.stray links its failures to docs/patterns.md#i-need-another-thing, but that pattern'
					. ' does not list it under Rules.',
			],
			array_map( static fn( string $problem ): string => explode( '. ', $problem )[0] . '.', $problems )
		);
	}

	public function test_it_finds_the_rules_a_configuration_registers(): void {
		$neon = implode(
			"\n",
			[
				'services:',
				'    helper:',
				'        class: Acme\\Helper',
				'    namedRule:',
				'        class: Acme\\NamedRule',
				'        tags: [phpstan.rules.rule]',
				'    -',
				'        class: Acme\\FirstRule',
				'        tags:',
				'            - phpstan.rules.rule',
				'    -',
				'        class: Acme\\NotARule',
			]
		);

		$this->assertSame( [ 'Acme\\NamedRule', 'Acme\\FirstRule' ], RuleRegistry::find_registered_rules( $neon ) );
	}
}
