<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

final class Registrations {

	public function work( callable $callback ): void {
		register_post_type( 'book' );
		register_post_type( 'acme_book' );
		register_taxonomy( 'genre', 'post' );
		register_taxonomy( 'acme_genre', 'post' );
		add_shortcode( 'gallery', $callback );
		add_shortcode( 'acme_plugin_gallery', $callback );
		register_rest_route( 'plugin/v1', '/items' );
		register_rest_route( 'acme/plugin/v1', '/items' );
		wp_schedule_event( 1, 'daily', 'refresh' );
		wp_schedule_event( 1, 'daily', 'acme_plugin_refresh' );
		wp_schedule_single_event( 1, 'refresh' );
		wp_schedule_single_event( 1, 'acme_plugin_refresh' );
		wp_next_scheduled( 'refresh' );
		wp_next_scheduled( 'acme_plugin_refresh' );
		wp_clear_scheduled_hook( 'refresh' );
		wp_clear_scheduled_hook( 'acme_plugin_refresh' );
		wp_unschedule_event( 1, 'refresh' );
		wp_unschedule_event( 1, 'acme_plugin_refresh' );
		add_menu_page( 'Title', 'Menu', 'manage_options', 'settings' );
		add_menu_page( 'Title', 'Menu', 'manage_options', 'acme-plugin-settings' );
		add_submenu_page( 'options-general.php', 'Title', 'Menu', 'manage_options', 'settings' );
		add_submenu_page( 'options-general.php', 'Title', 'Menu', 'manage_options', 'acme-plugin-settings' );
		add_options_page( 'Title', 'Menu', 'manage_options', 'settings' );
		add_options_page( 'Title', 'Menu', 'manage_options', 'acme-plugin-settings' );
		add_management_page( 'Title', 'Menu', 'manage_options', 'settings' );
		add_management_page( 'Title', 'Menu', 'manage_options', 'acme-plugin-settings' );
		register_setting( 'group', 'acme_plugin_settings' );
		register_setting( 'acme_plugin_group', 'acme_plugin_settings' );
		register_setting( 'acme_plugin_group', 'settings' );
		register_setting( 'acme_plugin_group', 'acme_plugin_settings' );
		add_action( 'wp_ajax_save', $callback );
		add_action( 'wp_ajax_acme_plugin_save', $callback );
		add_action( 'wp_ajax_nopriv_save', $callback );
		add_action( 'wp_ajax_nopriv_acme_plugin_save', $callback );
		register_setting( 'reading', 'acme_plugin_max_age' );
		register_setting( 'reading', 'max_age' );
		register_setting( 'discussion', 'acme_plugin_max_age' );
		register_setting( 'reading', 'reading' );
	}
}
