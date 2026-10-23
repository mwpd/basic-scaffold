<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\View\SafeHtml;
use MWPD\BasicScaffold\Infrastructure\View\SimpleTemplateScope;
use MWPD\BasicScaffold\Tests\Fixture\View\FixedGreeting;
use MWPD\BasicScaffold\Tests\Fixture\View\Greeting;
use MWPD\BasicScaffold\Tests\Fixture\View\Label;

/**
 * Results of presenter methods, as the template scope returns them.
 *
 * A template calls $this->greeting(), which PHP turns into __call(); the tests
 * call __call() directly to compare results by identity.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\View\SimpleTemplateScope
 */
final class SimpleTemplateScopeTest extends TestCase {

	public function test_strings_and_stringable_results_are_escaped(): void {
		$scope = $this->scope();

		$this->assertSame( 'escaped:Hello, Ada!', $scope->__call( 'greeting', [ 'Ada' ] ) );
		$this->assertSame( 'escaped:<label>', $scope->__call( 'label', [] ) );
	}

	public function test_safe_html_comes_back_as_the_same_object(): void {
		$presenter = $this->createMock( Greeting::class );
		$markup    = new SafeHtml( '<em>welcome</em>' );
		$presenter->method( 'emphasis' )->willReturn( $markup );

		$this->assertSame( $markup, $this->scope( $presenter )->__call( 'emphasis', [] ) );
	}

	public function test_other_results_come_back_unchanged(): void {
		$scope = $this->scope();

		$this->assertTrue( $scope->__call( 'is_formal', [] ) );
		$this->assertSame( 3, $scope->__call( 'count_guests', [] ) );
		$this->assertSame( [ '<b>Ada</b>', 'Grace' ], $scope->__call( 'guest_list', [] ) );
		$this->assertNull( $scope->__call( 'note', [] ) );
	}

	/**
	 * Create a scope over a Greeting, with an escaping function that marks
	 * what it escaped.
	 *
	 * @param ?Greeting $presenter Presenter. Optional, defaults to FixedGreeting.
	 * @return SimpleTemplateScope Scope.
	 */
	private function scope( ?Greeting $presenter = null ): SimpleTemplateScope {
		$methods = [];
		foreach ( \get_class_methods( Greeting::class ) as $method ) {
			$methods[ \strtolower( $method ) ] = $method;
		}

		return new SimpleTemplateScope(
			$this->createMock( View::class ),
			static function ( $value ): string {
				\assert( \is_string( $value ) || $value instanceof Label );

				return 'escaped:' . $value;
			},
			$presenter ?? new FixedGreeting(),
			$methods
		);
	}
}
