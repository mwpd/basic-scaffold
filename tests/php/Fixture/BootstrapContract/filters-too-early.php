<?php
/**
 * A bootstrap that otherwise keeps the contract, but fires an extension early.
 *
 * @package MWPD\BasicScaffold\Tests
 */

declare( strict_types=1 );

register_activation_hook( __FILE__, static function (): void {} );
register_deactivation_hook( __FILE__, static function (): void {} );
add_action( 'plugins_loaded', static function (): void {}, 10, 0 );
apply_filters( 'fixture.services', [] );
