<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Integration;

use MWPD\BasicScaffold\Infrastructure\View\SimpleView;
use MWPD\BasicScaffold\Infrastructure\View\SimpleViewFactory;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class SimpleViewTest extends TestCase {

	public function test_it_loads_partials_across_overrides(): void {
		$configuration = new ViewConfiguration( ViewHelper::PLUGIN_ROOT, ViewHelper::OVERRIDE_DIRECTORY );
		$view          = new SimpleView(
			ViewHelper::VIEWS_FOLDER . 'static-view',
			new SimpleViewFactory( null, $configuration ),
			null,
			$configuration
		);

		$this->assertStringStartsWith(
			'<p>Rendering works.</p>',
			$view->render()
		);
	}
}
