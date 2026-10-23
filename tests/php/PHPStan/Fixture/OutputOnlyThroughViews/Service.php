<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

use MWPD\BasicScaffold\Infrastructure\View;
use function printf as output_format;

final class Service {

	public function output( string $value, View $view, PretendView $pretend ): void {
		$rendered = $view->render();

		echo '<p>Literal HTML</p>';
		echo $value;
		print $value;
		printf( '<p>%s</p>', $value );
		vprintf( '<p>%s</p>', [ $value ] );
		\PRINTF( '%s', $value );
		output_format( '%s', $value );
		echo $view->render(), $value;
		echo $value, $view->render();
		echo $pretend->render();
		echo $rendered;
		// Concatenating HTML around a view still builds markup in the service.
		echo '<div>' . $view->render() . '</div>';
		echo $value, '<p>Another literal</p>';
	}
}
