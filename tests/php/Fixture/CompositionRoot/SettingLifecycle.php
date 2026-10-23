<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class SettingLifecycle implements Service, Registerable, Activateable, Deactivateable {

	public function register(): void {
		add_option( 'fixture_setting', 'ready' );
		update_option( 'fixture_updated', 'ready' );
		set_transient( 'fixture_cache', 'ready', 60 );
	}

	public function activate(): void {
		add_post_meta( 42, 'fixture_unique', 'ready', true );
		update_user_meta( 42, 'fixture_setting', 'ready' );
		update_term_meta( 42, 'fixture_setting', 'ready' );
		update_comment_meta( 42, 'fixture_setting', 'ready' );
	}

	public function deactivate(): void {
		delete_option( 'fixture_setting' );
		delete_transient( 'fixture_cache' );
		delete_post_meta( 42, 'fixture_unique' );
		delete_user_meta( 42, 'fixture_setting' );
		delete_term_meta( 42, 'fixture_setting' );
		delete_comment_meta( 42, 'fixture_setting' );
	}
}
