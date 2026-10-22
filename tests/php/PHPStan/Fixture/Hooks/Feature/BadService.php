<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Registerable;

final class BadService implements Registerable {

	public function __construct() {
		add_filter( 'the_content', [ $this, 'filter' ] );
		$later = function (): void {
			add_action( 'init', [ $this, 'filter' ] );
		};
	}

	public function register(): void {
		if ( is_admin() ) {
			add_action( 'admin_notices', [ $this, 'helper' ] );
		}

		add_action(
			'init',
			function (): void {
				add_filter( 'the_title', [ $this, 'filter' ] );
				is_admin();
			}
		);
	}

	private function helper(): void {
		add_action( 'wp', [ $this, 'filter' ] );
	}

	public function filter(): void {
		remove_action( 'wp', [ $this, 'filter' ] );
	}

	public function arrow(): callable {
		return fn() => add_action( 'shutdown', [ $this, 'filter' ] );
	}
}
