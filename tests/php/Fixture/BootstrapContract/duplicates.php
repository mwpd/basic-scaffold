<?php
/**
 * Duplicate calls violate the contract even when hook storage would deduplicate.
 *
 * @package MWPD\BasicScaffold\Tests
 */

declare( strict_types=1 );

$callback = static function (): void {};
register_activation_hook( __FILE__, $callback );
register_deactivation_hook( __FILE__, $callback );
add_action( 'plugins_loaded', $callback, 10, 0 );

add_action( 'plugins_loaded', $callback, 10, 0 );
register_activation_hook( __FILE__, $callback );
register_deactivation_hook( __FILE__, $callback );
