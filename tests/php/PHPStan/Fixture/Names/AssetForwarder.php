<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

final class AssetForwarder {
	public function forward( string $handle ): void {
		wp_register_style( $handle, 'https://example.com/a.css' );
		wp_enqueue_style( $handle );
		wp_register_style( 'its-own', 'https://example.com/b.css' );
	}
}
