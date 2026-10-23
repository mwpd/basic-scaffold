<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

use MWPD\BasicScaffold\Infrastructure\Renderable;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;

final class RenderedOutput {

	public function output( View $view, Renderable $renderable, ViewFactory $factory ): void {
		echo $view->render( [ 'name' => 'Example' ] );
		echo $renderable->render();
		echo $factory->create( 'views/notice' )->render();
		echo $view->render(), $renderable->render();
		print $view->render();
		echo $view->render_partial( 'views/partial' );
		printf( $view->render() );
		sprintf( '<p>%s</p>', 'Not output' );
		wp_send_json( [ 'status' => 'Outside this rule' ] );
	}
}
