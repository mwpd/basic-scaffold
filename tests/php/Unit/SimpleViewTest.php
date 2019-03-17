<?php

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Infrastructure\View\SimpleView;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class SimpleViewTest extends TestCase {

	public function test_it_can_be_initialized(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new SimpleView(
			ViewHelper::VIEWS_FOLDER . 'static-view',
			$view_factory_mock
		);

		$this->assertInstanceOf( SimpleView::class, $view );
	}

	public function test_it_can_be_rendered(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new SimpleView(
			ViewHelper::VIEWS_FOLDER . 'static-view',
			$view_factory_mock
		);

		$this->assertStringStartsWith(
			'<p>Rendering works.</p>',
			$view->render()
		);
	}

	public function test_it_can_provide_rendering_context(): void {
		$view_factory_mock = $this->createMock( ViewFactory::class );
		$view              = new SimpleView(
			ViewHelper::VIEWS_FOLDER . 'dynamic-view',
			$view_factory_mock
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
			->with( ViewHelper::VIEWS_FOLDER . 'partial' )
			->willReturn( new SimpleView(
				ViewHelper::VIEWS_FOLDER . 'partial',
				$view_factory_mock
			) );

		$view = new SimpleView(
			ViewHelper::VIEWS_FOLDER . 'view-with-partial',
			$view_factory_mock
		);

		$this->assertStringStartsWith(
			'<p>Rendering works with partials: <span>42</span>.</p>',
			$view->render( [ 'some_value' => 42 ] )
		);
	}
}
