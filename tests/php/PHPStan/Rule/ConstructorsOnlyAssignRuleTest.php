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
 * @extends RuleTestCase<ConstructorsOnlyAssignRule>
 */
final class ConstructorsOnlyAssignRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/ConstructorsOnlyAssign/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign';

	private const TIP = 'Principle 7: Show the alternative to WordPress anti-patterns, see '
		. 'docs/design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns. '
		. 'Pattern: docs/patterns.md#i-need-to-add-hooks. '
		. 'Rule: docs/architecture-rules.md#architectureconstructorsonlyassign';

	/** @var array<string> */
	private array $allowed = [];

	protected function getRule(): Rule {
		return new ConstructorsOnlyAssignRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			'MWPD\BasicScaffold\Infrastructure',
			$this->allowed,
			$this->createReflectionProvider()
		);
	}

	public function test_it_reports_calls_and_branches_with_guidance(): void {
		$this->analyse(
			[ self::FIXTURES . 'CallService.php', self::FIXTURES . 'BranchService.php' ],
			[
				[ $this->message( 'CallService' ), 12, self::TIP ],
				[ $this->message( 'BranchService' ), 12, self::TIP ],
			]
		);
	}

	public function test_it_allows_assignments_defaults_comments_and_parent_construction(): void {
		$this->analyse(
			[ self::FIXTURES . 'Assignments.php', self::FIXTURES . 'WidgetService.php' ],
			[]
		);
	}

	public function test_it_rejects_computed_values_and_invalid_defaults(): void {
		$errors = [];
		foreach ( range( 13, 18 ) as $line ) {
			$errors[] = [ $this->message( 'InvalidAssignments' ), $line, self::TIP ];
		}
		$this->analyse( [ self::FIXTURES . 'InvalidAssignments.php' ], $errors );
	}

	public function test_it_respects_method_allowances_case_insensitively(): void {
		$this->allowed = [ '\\' . strtoupper( self::FIXTURE_NAMESPACE . '\CallService::__construct' ) ];
		$this->analyse( [ self::FIXTURES . 'CallService.php' ], [] );
	}

	public function test_it_ignores_non_services_and_code_outside_the_plugin(): void {
		$this->analyse(
			[
				self::FIXTURES . 'NotService.php',
				self::FIXTURES . 'Excluded/Service.php',
				self::FIXTURES . 'outside.php',
			],
			[]
		);
	}

	public function test_it_covers_services_inherited_through_an_interface(): void {
		$this->analyse(
			[ self::FIXTURES . 'IndirectService.php' ],
			[ [ $this->message( 'IndirectService' ), 10, self::TIP ] ]
		);
	}

	public function test_it_excludes_bootstrap_injectors(): void {
		$this->analyse( [ self::FIXTURES . 'BootstrapInjector.php' ], [] );
	}

	private function message( string $owner ): string {
		return self::FIXTURE_NAMESPACE . '\\' . $owner . '::__construct() does work beyond storing dependencies.'
			. ' Keep only parameter-to-property assignments and poka-yoke defaults in service constructors.'
			. ' Move the work into register() or a callback, or into a factory if it is construction logic.';
	}
}
