<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

final class NameExpressions {

	private const POST_TYPE_PREFIX = 'acme_';
	private const TAXONOMY_PREFIX = 'acme_';
	private const SHORTCODE_PREFIX = 'acme_plugin_';
	private const REST_PREFIX = 'acme/plugin';
	private const CRON_PREFIX = 'acme_plugin_';
	private const ADMIN_PREFIX = 'acme-plugin-';
	private const SETTING_PREFIX = 'acme_plugin_';
	private const AJAX_PREFIX = 'acme_plugin_';

	public function work( string $name, callable $callback, bool $public ): void {
		register_post_type( self::POST_TYPE_PREFIX . $name );
		register_taxonomy( static::TAXONOMY_PREFIX . $name, 'post' );
		register_post_type( self::POST_TYPE_PREFIX . '1234567890123456' );
		register_taxonomy( self::TAXONOMY_PREFIX . '1234567890123456789012345678', 'post' );
		add_shortcode( self::SHORTCODE_PREFIX . $name, $callback );
		register_rest_route( self::REST_PREFIX . $name, '/items' );
		wp_schedule_event( 1, 'daily', self::CRON_PREFIX . $name );
		add_menu_page( 'Title', 'Menu', 'manage_options', self::ADMIN_PREFIX . $name );
		register_setting( self::SETTING_PREFIX . $name, static::SETTING_PREFIX . $name );
		add_action( 'wp_ajax_' . self::AJAX_PREFIX . $name, $callback );
		add_action( 'wp_ajax_nopriv_' . static::AJAX_PREFIX . $name, $callback );
		add_action( 'init', $callback );
		add_action( $name, $callback );
		add_action( 'other_' . $name, $callback );
		add_filter( 'wp_ajax_save', $callback );
		add_action( ...[ 'wp_ajax_acme_plugin_save', $callback ] );
		register_setting( ...[ 'acme_plugin_group', 'acme_plugin_name' ] );
		wp_next_scheduled( 'core_event' );
		wp_clear_scheduled_hook( 'core_event' );
		add_shortcode( $name, $callback );
		add_action( 'wp_ajax_' . $name, $callback );
		add_action( 'wp_ajax_nopriv_' . $name, $callback );
		add_action( $public ? 'init' : 'wp_ajax_save', $callback );
		add_action( 'wp_ajax_nopriv_', $callback );
		register_setting( 'group', 'name' );
		register_post_type( ...[ 'acme_1234567890123456' ] );
	}
}
