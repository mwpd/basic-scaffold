<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Activateable;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Registerable;

final class GoodService implements Registerable, Activateable, Deactivateable {

	public static function is_needed(): bool {
		return is_admin();
	}

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	public function activate(): void {
		add_filter( 'option_x', [ $this, 'filter' ] );
	}

	public function deactivate(): void {
		remove_filter( 'option_x', [ $this, 'filter' ] );
	}

	public function render(): void {
		if ( is_singular() ) {
			echo 'Singular.';
		}
	}
}
