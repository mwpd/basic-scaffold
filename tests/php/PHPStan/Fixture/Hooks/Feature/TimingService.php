<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Registerable;

final class TimingService implements Registerable {

	public function register(): void {
		did_action( 'init' );
		doing_action( 'init' );
		did_filter( 'the_content' );
		doing_filter( 'the_content' );
		current_action();
		current_filter();

		add_action(
			'init',
			static function (): void {
				did_action( 'init' );
				doing_action( 'init' );
				did_filter( 'the_content' );
				doing_filter( 'the_content' );
				current_action();
				current_filter();
			}
		);
		add_action( 'init', static fn() => did_action( 'init' ) );
	}

	public function callback(): void {
		did_action( 'init' );
	}
}
