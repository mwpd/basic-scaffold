<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

final class Service {

	private const HOOK_PREFIX = 'acme.plugin.';

	public function work( string $name ): void {
		do_action( 'settings_saved' );
		do_action( self::HOOK_PREFIX . 'settings_saved' );
		do_action( 'acme.plugin.settings_saved' );
		apply_filters( $name, [] );
		update_option( 'settings', [] );
		get_option( 'date_format' );
		update_option( 'date_format', 'Y' );
		get_option( 'acme_plugin_settings' );
		set_transient( 'cache', [] );
		register_post_meta( 'post', 'color', [] );
		wp_register_script( 'app', 'app.js' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'theme' );
		wp_enqueue_style( 'acme-plugin-admin' );
		register_post_meta( ...[ 'post', 'color', [] ] );
		do_action( ...[ 'acme.plugin.unpacked' ] );
		do_action( ...$this->names() );
		do_action( Other::HOOK_PREFIX . 'saved' );
		wp_enqueue_script( 'jquery', '/plugin/app.js' );
		wp_enqueue_script( 'jquery', '' );
	}

	/**
	 * @return array<string>
	 */
	private function names(): array {
		return [ 'acme.plugin.dynamic' ];
	}
}

final class Other {

	public const HOOK_PREFIX = 'other.';
}
