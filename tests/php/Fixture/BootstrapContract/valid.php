<?php
/**
 * Exactly the three entry hooks; executing any callback would fail the check.
 *
 * @package MWPD\BasicScaffold\Tests
 */

declare( strict_types=1 );

$callback = static function (): void {
	throw new LogicException( 'Bootstrap callbacks must wait for WordPress.' );
};

register_activation_hook( __FILE__, $callback );
register_deactivation_hook( __FILE__, $callback );
add_action( 'plugins_loaded', $callback, 10, 0 );
