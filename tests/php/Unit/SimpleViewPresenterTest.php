<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use LogicException;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPresenterMethod;
use MWPD\BasicScaffold\Infrastructure\Presenter;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\View\SafeHtml;
use MWPD\BasicScaffold\Infrastructure\View\SimpleViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Tests\Fixture\View\FixedGreeting;
use MWPD\BasicScaffold\Tests\Fixture\View\FixedRawGreeting;
use MWPD\BasicScaffold\Tests\Fixture\View\Greeting;
use MWPD\BasicScaffold\Tests\Fixture\View\Label;
use MWPD\BasicScaffold\Tests\ViewHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Throwable;

/**
 * Presenter methods that templates call through $this.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\View\SimpleView
 * @covers \MWPD\BasicScaffold\Infrastructure\View\SimpleTemplateScope
 * @covers \MWPD\BasicScaffold\Infrastructure\View\SafeHtml
 * @covers \MWPD\BasicScaffold\Exception\InvalidPresenterMethod
 */
final class SimpleViewPresenterTest extends TestCase {

	/** @var MockObject&DebugMode */
	private MockObject $debug_mode;

	protected function setUp(): void {
		$this->debug_mode = $this->createMock( DebugMode::class );
	}

	public function test_a_template_calls_presenter_methods_and_their_results_are_escaped_by_kind(): void {
		$result = $this->view( 'presenter-view', false )->render( [], new FixedGreeting() );

		// A boolean drives the branch unchanged.
		$this->assertStringContainsString( '<p>formal</p>', $result );
		// Strings are escaped, and arguments arrive by position, defaults included.
		$this->assertStringContainsString( '<p>Hello, &lt;b&gt;Ada&lt;/b&gt;!</p>', $result );
		$this->assertStringContainsString( '<p>Hello, Grace?</p>', $result );
		// SafeHtml is the presenter's own markup.
		$this->assertStringContainsString( '<p><em>welcome</em></p>', $result );
		// A stringable object is escaped like a string.
		$this->assertStringContainsString( '<p>&lt;label&gt;</p>', $result );
		// Other values pass through.
		$this->assertStringContainsString( '<p>3 guests</p>', $result );
	}

	public function test_method_names_are_case_insensitive_as_in_php(): void {
		$result = $this->view( 'presenter-call-view', true )
			->render( [ 'method' => 'IS_FORMAL' ], new FixedGreeting() );

		$this->assertStringContainsString( 'Called the method.', $result );
	}

	/**
	 * @dataProvider invalid_calls
	 *
	 * @param string                  $method    Method the template calls.
	 * @param ?Presenter              $presenter Presenter the view renders with.
	 * @param class-string<Throwable> $cause     Cause the failure surfaces with.
	 */
	public function test_an_invalid_call_fails_the_view_with_its_cause_in_debug_mode(
		string $method,
		?Presenter $presenter,
		string $cause
	): void {
		$level = \ob_get_level();

		try {
			$this->view( 'presenter-call-view', true )->render( [ 'method' => $method ], $presenter );
			$this->fail( 'An invalid call should throw in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$this->assertInstanceOf( $cause, $exception->getPrevious() );
		}

		$this->assertSame( $level, \ob_get_level(), 'The view leaked an output buffer.' );
	}

	/**
	 * @dataProvider invalid_calls
	 *
	 * @param string     $method    Method the template calls.
	 * @param ?Presenter $presenter Presenter the view renders with.
	 */
	public function test_an_invalid_call_renders_nothing_in_production( string $method, ?Presenter $presenter ): void {
		$level = \ob_get_level();

		$result = $this->view( 'presenter-call-view', false )->render( [ 'method' => $method ], $presenter );

		$this->assertSame( '', $result );
		$this->assertSame( $level, \ob_get_level(), 'The view leaked an output buffer.' );
	}

	/**
	 * The ways a call on $this can fail: no presenter, a public method no
	 * Presenter interface declares, a presenter that throws, and a presenter
	 * method the scope's own method of the same name shadows.
	 *
	 * @return array<string, array{string, ?Presenter, class-string<Throwable>}>
	 */
	public static function invalid_calls(): array {
		return [
			'no presenter'       => [ 'is_formal', null, InvalidPresenterMethod::class ],
			'undeclared method'  => [ 'undeclared', new FixedGreeting(), InvalidPresenterMethod::class ],
			'unknown method'     => [ 'missing', new FixedGreeting(), InvalidPresenterMethod::class ],
			'throwing presenter' => [ 'failing', new FixedGreeting(), LogicException::class ],
		];
	}

	public function test_a_presenter_method_the_scope_shadows_fails_even_a_template_that_never_calls_it(): void {
		try {
			$this->view( 'static-view', true )->render( [], new FixedRawGreeting() );
			$this->fail( 'A reserved presenter method name should fail the view in debug mode.' );
		} catch ( FailedToLoadView $exception ) {
			$cause = $exception->getPrevious();
			$this->assertInstanceOf( InvalidPresenterMethod::class, $cause );
			$this->assertStringContainsString(
				'declares the method "raw()", which templates cannot call',
				$cause->getMessage()
			);
		}
	}

	public function test_a_presenter_method_the_scope_shadows_renders_nothing_in_production(): void {
		$this->assertSame( '', $this->view( 'static-view', false )->render( [], new FixedRawGreeting() ) );
	}

	public function test_the_presenter_only_holds_for_its_own_render(): void {
		$view = $this->view( 'presenter-with-partial-view', false );
		$view->render( [ 'partial_presenter' => null ], new FixedGreeting() );

		// The partial calls the presenter, and there is none outside a render.
		$this->assertSame( '', $view->render_partial( 'tests/php/Fixture/views/plugin/presenter-partial', [] ) );
	}

	public function test_a_nested_render_of_the_same_view_does_not_change_the_outer_presenter(): void {
		$view = $this->view( 'presenter-with-partial-view', false );

		// The outer presenter renders the same view again, with a presenter
		// of its own, the first time the outer template asks it for a
		// greeting.
		$outer = new class( $view ) implements Greeting {

			private View $view;

			private bool $nested = false;

			public function __construct( View $view ) {
				$this->view = $view;
			}

			public function greeting( string $name, string $punctuation = '!' ): string {
				if ( ! $this->nested ) {
					$this->nested = true;
					$this->view->render( [ 'partial_presenter' => null ], new FixedGreeting( 'Inner' ) );
				}

				return 'Outer, ' . $name . $punctuation;
			}

			public function is_formal(): bool {
				return false;
			}

			public function emphasis(): SafeHtml {
				return new SafeHtml( '' );
			}

			public function label(): Label {
				return new Label( '' );
			}

			public function count_guests(): int {
				return 0;
			}

			/**
			 * @return list<string>
			 */
			public function guest_list(): array {
				return [];
			}

			public function note(): ?string {
				return null;
			}

			public function failing(): string {
				return '';
			}
		};

		$result = $view->render( [ 'partial_presenter' => null ], $outer );

		$this->assertStringContainsString( '<p>Partial: Outer, partial!</p>', $result );
	}

	public function test_a_partial_inherits_the_presenter_or_takes_its_own(): void {
		$view = $this->view( 'presenter-with-partial-view', false );

		$inherited = $view->render( [ 'partial_presenter' => null ], new FixedGreeting() );
		$own       = $view->render( [ 'partial_presenter' => new FixedGreeting( 'Hi' ) ], new FixedGreeting() );

		$this->assertStringContainsString( '<p>Partial: Hello, partial!</p>', $inherited );
		$this->assertStringContainsString( '<p>Partial: Hi, partial!</p>', $own );
		$this->assertStringContainsString( '<p>Parent again: Hello, parent!</p>', $own );
	}

	public function test_a_failing_partial_leaves_the_parent_output_intact_in_production(): void {
		$level  = \ob_get_level();
		$result = $this->view( 'presenter-with-partial-view', false )->render(
			[
				'partial_presenter' => null,
				'fail'              => true,
			],
			new FixedGreeting()
		);

		$this->assertSame( $level, \ob_get_level(), 'The view leaked an output buffer.' );
		$this->assertStringContainsString( '<p>Parent: Hello, parent!</p>', $result );
		$this->assertStringNotContainsString( 'Partial:', $result );
		$this->assertStringContainsString( '<p>Parent again: Hello, parent!</p>', $result );
	}

	/**
	 * Create a view of a plugin fixture template.
	 *
	 * @param string $template   Name of the template in the plugin fixtures.
	 * @param bool   $debug_mode Whether debug mode is on.
	 * @return View View of the template.
	 */
	private function view( string $template, bool $debug_mode ): View {
		$this->debug_mode->method( 'is_debug_mode' )->willReturn( $debug_mode );

		$configuration = new ViewConfiguration( ViewHelper::PLUGIN_ROOT, ViewHelper::OVERRIDE_DIRECTORY );

		return ( new SimpleViewFactory( $this->debug_mode, $configuration ) )
			->create( 'tests/php/Fixture/views/plugin/' . $template );
	}
}
