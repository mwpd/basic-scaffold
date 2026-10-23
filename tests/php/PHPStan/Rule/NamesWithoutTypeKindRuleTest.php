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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NamesWithoutTypeKindRule>
 */
final class NamesWithoutTypeKindRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/NamesWithoutTypeKind/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKind';

	private const TIP = 'Principle 8: Types name concepts, not just data, see '
		. 'docs/design-principles.md#8-types-name-concepts-not-just-data. '
		. 'Pattern: docs/patterns.md#i-need-to-start-simple-and-add-implementations-later. '
		. 'Rule: docs/architecture-rules.md#architecturenameswithouttypekind';

	private string $namespace = self::FIXTURE_NAMESPACE;

	private string $marker = self::FIXTURE_NAMESPACE . '\PluginException';

	/**
	 * Reviewed allowances, using fully qualified names.
	 *
	 * @var array<string>
	 */
	private array $allowed = [];

	protected function getRule(): Rule {
		return new NamesWithoutTypeKindRule(
			new PluginNamespace( $this->namespace, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			$this->marker,
			$this->allowed
		);
	}

	public function test_it_reports_every_type_kind_marker_with_the_full_message(): void {
		$this->analyse(
			[ self::FIXTURES . 'invalid.php' ],
			[
				[ $this->message( 'FooInterface', 'Foo' ), 7, self::TIP ],
				[ $this->message( 'IFoo', 'Foo' ), 10, self::TIP ],
				[ $this->message( 'AbstractFoo', 'Foo' ), 13, self::TIP ],
				[ $this->message( 'BaseFoo', 'Foo' ), 16, self::TIP ],
				[ $this->message( 'FooTrait', 'Foo' ), 19, self::TIP ],
				[ $this->message( 'FooImpl', 'Foo' ), 22, self::TIP ],
				[ $this->message( 'FooException', 'Foo' ), 25, self::TIP ],
				[ $this->message( 'FooAbstract', 'Foo' ), 28, self::TIP ],
				[ $this->message( 'FooImplementation', 'Foo' ), 31, self::TIP ],
				[ $this->message( 'FooClass', 'Foo' ), 34, self::TIP ],
				[ $this->message( 'AbstractBarInterface', 'Bar' ), 37, self::TIP ],
			]
		);
	}

	public function test_it_allows_concepts_distinguishing_names_roles_capabilities_and_anonymous_classes(): void {
		$this->analyse( [ self::FIXTURES . 'valid.php' ], [] );
	}

	public function test_it_uses_the_configured_marker_and_normalizes_its_name(): void {
		$this->marker = '\\' . strtolower( self::FIXTURE_NAMESPACE ) . '\AnotherException';
		$this->analyse(
			[ self::FIXTURES . 'marker.php' ],
			[
				[ $this->message( 'RelatedException', 'Related' ), 10, self::TIP ],
			]
		);
	}

	public function test_it_does_not_allow_other_exception_interfaces_or_inheriting_exceptions(): void {
		$this->analyse(
			[ self::FIXTURES . 'marker.php' ],
			[
				[ $this->message( 'AnotherException', 'Another' ), 7, self::TIP ],
				[ $this->message( 'RelatedException', 'Related' ), 10, self::TIP ],
			]
		);
	}

	public function test_it_honours_only_the_fully_qualified_reviewed_allowances(): void {
		$this->allowed = [ '\\' . strtolower( self::FIXTURE_NAMESPACE ) . '\FooInterface' ];
		$this->analyse(
			[ self::FIXTURES . 'allowed.php' ],
			[
				[ $this->message( 'Other\FooInterface', 'Foo' ), 12, self::TIP ],
			]
		);
	}

	public function test_it_skips_excluded_namespaces_and_names_outside_the_plugin(): void {
		$this->analyse( [ self::FIXTURES . 'excluded.php', self::FIXTURES . 'renamed.php' ], [] );
	}

	public function test_it_follows_a_renamed_plugin_and_marker(): void {
		$this->namespace = 'Acme\RenamedPlugin';
		$this->marker    = 'Acme\RenamedPlugin\RenamedException';
		$this->analyse(
			[ self::FIXTURES . 'renamed.php', self::FIXTURES . 'invalid.php' ],
			[
				[ $this->message( 'FooInterface', 'Foo', $this->namespace ), 7, self::TIP ],
			]
		);
	}

	private function message(
		string $name,
		string $suggestion,
		string $type_namespace = self::FIXTURE_NAMESPACE
	): string {
		return sprintf(
			'Type %s encodes its kind in its name. Use %s instead, so promoting a class to an interface keeps'
			. ' type hints, instanceof and catch unchanged. Name implementations by what distinguishes them;'
			. ' reviewed exceptions belong in parameters.architecture.namesWithoutTypeKind.allowed.',
			$type_namespace . '\\' . $name,
			$suggestion
		);
	}
}
