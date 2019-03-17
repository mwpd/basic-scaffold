<?php

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Infrastructure\View\TemplatedView;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class TemplatedViewTest extends TestCase {

	public function test_it_can_be_initialized(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new TemplatedView(
			'static-view',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);

		$this->assertInstanceOf( TemplatedView::class, $view );
	}

	public function test_it_can_be_rendered(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new TemplatedView(
			'static-view',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);

		$this->assertStringStartsWith(
			'<p>Rendering works.</p>',
			$view->render()
		);
	}

	public function test_it_can_provide_rendering_context(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new TemplatedView(
			'dynamic-view',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);

		$this->assertStringStartsWith(
			'<p>Rendering works with context: 42.</p>',
			$view->render( [ 'some_value' => 42 ] )
		);
	}

	public function test_it_can_render_partials(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view_factory_mock
			->expects( $this->once() )
			->method( 'create' )
			->with( 'partial' )
			->willReturn( new TemplatedView(
				'partial',
				$view_factory_mock,
				ViewHelper::LOCATIONS
			) );

		$view = new TemplatedView(
			'view-with-partial',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);

		$this->assertStringStartsWith(
			'<p>Rendering works with partials: <span>42</span>.</p>',
			$view->render( [ 'some_value' => 42 ] )
		);
	}

	public function test_it_can_be_overridden_in_themes(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view_a            = new TemplatedView(
			'view-a',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);
		$view_b            = new TemplatedView(
			'view-b',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);
		$view_c            = new TemplatedView(
			'view-c',
			$view_factory_mock,
			ViewHelper::LOCATIONS
		);

		$this->assertStringStartsWith(
			'<p>View A comes from plugin.</p>',
			$view_a->render()
		);
		$this->assertStringStartsWith(
			'<p>View B comes from parent theme.</p>',
			$view_b->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from child theme.</p>',
			$view_c->render()
		);
	}
}
