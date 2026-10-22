<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks;

final class Plugin {

	public function boot(): void {
		add_action( 'plugins_loaded', [ $this, 'boot' ] );
	}
}
