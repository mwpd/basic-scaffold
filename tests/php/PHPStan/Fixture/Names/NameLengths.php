<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

final class NameLengths {

	public function work( callable $callback ): void {
		register_post_type( 'acme_123456789012345' );
		register_post_type( 'acme_1234567890123456' );
		register_taxonomy( 'acme_123456789012345678901234567', 'post' );
		register_taxonomy( 'acme_1234567890123456789012345678', 'post' );
	}
}
