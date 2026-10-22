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
		$root       = dirname( __DIR__, 3 );
		$neon       = (string) file_get_contents( $root . '/tests/php/PHPStan/rules.neon' );
		$catalogue  = (string) file_get_contents( $root . '/' . RuleManifest::CATALOGUE );
		$rules      = [];
		$sources    = [];
		$candidates = array_merge(
			(array) glob( $root . '/tests/php/PHPStan/Rule/*.php' ),
			(array) glob( $root . '/tests/php/Architecture/*.php' )
		);

		foreach ( $candidates as $path ) {
			$path = (string) $path;
			if ( substr( $path, -8 ) === 'Test.php' || basename( $path ) === 'RuleManifest.php' ) {
				continue;
			}

			$sources[ substr( $path, strlen( $root ) + 1 ) ] = (string) file_get_contents( $path );

			foreach ( PhpFile::from_path( $path )->get_declarations() as $declaration ) {
				$name = $declaration['name'];
				if ( class_exists( $name ) && ( new ReflectionClass( $name ) )->implementsInterface( Rule::class ) ) {
					$rules[ $name ] = $sources[ substr( $path, strlen( $root ) + 1 ) ];
				}
			}
		}

		$problems = RuleRegistry::find_problems(
			$rules,
			RuleRegistry::find_registered_rules( $neon ),
			$sources,
			RuleManifest::get_constants(),
			RuleRegistry::find_documented_rules( $catalogue )
		);

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_rules_that_are_not_registered_listed_or_documented(): void {
		$problems = RuleRegistry::find_problems(
			[ 'Acme\UnregisteredRule' => '' ],
			[ 'Acme\RegisteredRule' ],
			[
				'Registered.php' => '->identifier( RuleManifest::KNOWN )',
				'Spelled.php'    => "->identifier( 'architecture.spelledOut' )",
			],
			[
				'KNOWN'  => 'architecture.known',
				'UNUSED' => 'architecture.unused',
			],
			[ 'architecture.known', 'architecture.removed' ]
		);

		$this->assertCount( 4, $problems, implode( "\n\n", $problems ) );
		$this->assertStringStartsWith( 'Acme\UnregisteredRule is a PHPStan rule, but', $problems[0] );
		$this->assertStringStartsWith( 'Spelled.php reports architecture.spelledOut by spelling', $problems[1] );
		$this->assertStringStartsWith( 'RuleManifest lists architecture.unused, but no rule', $problems[2] );
		$this->assertStringStartsWith( 'docs/architecture-rules.md documents architecture.removed', $problems[3] );
	}

	public function test_it_finds_the_rules_a_configuration_registers(): void {
		$neon = implode(
			"\n",
			[
				'services:',
				'    helper:',
				'        class: Acme\Helper',
				'',
				'    -',
				'        class: Acme\FirstRule',
				'        tags:',
				'            - phpstan.rules.rule',
				'',
				'    -',
				'        class: Acme\NotARule',
				'',
				'    -',
				'        class: Acme\SecondRule',
				'        tags:',
				'            - phpstan.rules.rule',
			]
		);

		$this->assertSame( [ 'Acme\FirstRule', 'Acme\SecondRule' ], RuleRegistry::find_registered_rules( $neon ) );
	}
}
