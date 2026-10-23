<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

echo 'File scope';

function output(): void {
	print 'Function scope';
}

$callback = static function (): void {
	echo 'Closure scope';
};
