<?php
/**
 * Rewrite the reviewed API listing with: composer api:snapshot.
 */

declare( strict_types=1 );

use MWPD\BasicScaffold\Tests\Architecture\InfrastructureApiSnapshot;
use MWPD\BasicScaffold\Tests\Architecture\RepositoryLayout;

require __DIR__ . '/../vendor/autoload.php';

$layout   = RepositoryLayout::of_plugin();
$snapshot = ( new InfrastructureApiSnapshot( $layout ) )->build();

if ( file_put_contents( $layout->absolute( InfrastructureApiSnapshot::PATH ), $snapshot ) === false ) {
	fwrite( STDERR, 'Could not write ' . InfrastructureApiSnapshot::PATH . ".\n" );
	exit( 1 );
}

echo 'Updated ' . InfrastructureApiSnapshot::PATH . ".\n";
