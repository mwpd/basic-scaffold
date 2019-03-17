<?php

namespace MWPD\BasicScaffold\Tests\Integration;

use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedView;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class TemplatedViewFactoryTest extends TestCase {

	public function test_it_can_create_views(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS );

		$view = $factory->create( 'static-view' );
		$this->assertInstanceOf( TemplatedView::class, $view );
	}

	public function test_created_views_implement_the_interface(): void {
		$factory = new TemplatedViewFactory( ViewHelper::LOCATIONS );

		$view = $factory->create( 'static-view' );
		$this->assertInstanceOf( View::class, $view );
	}
}
