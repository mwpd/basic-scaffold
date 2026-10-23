<?php
/**
 * Matching function names alone do not make valid registrations.
 *
 * @package MWPD\BasicScaffold\Tests
 */

declare( strict_types=1 );

add_action( 'plugins_loaded', 'missing_bootstrap_callback' );
register_activation_hook( 'another-plugin.php', static function (): void {} );
register_deactivation_hook( __FILE__, 'missing_bootstrap_callback' );
