<?php
/**
 * Hook operations that the bootstrap must defer, even if they undo each other.
 *
 * @package MWPD\BasicScaffold\Tests
 */

declare( strict_types=1 );

$callback = static function (): void {};
register_activation_hook( __FILE__, $callback );
register_deactivation_hook( __FILE__, $callback );
add_action( 'plugins_loaded', $callback, 10, 0 );

add_action( 'init', $callback );
add_filter( 'fixture.services', $callback );
remove_action( 'init', $callback );
remove_filter( 'fixture.services', $callback );
remove_all_actions( 'init' );
remove_all_filters( 'fixture.services' );
do_action( 'fixture.loaded' );
do_action_ref_array( 'fixture.loaded', [] );
do_action_deprecated( 'fixture.loaded', [], '1.0', 'fixture.ready' );
apply_filters( 'fixture.services', [] );
apply_filters_ref_array( 'fixture.services', [ [] ] );
apply_filters_deprecated( 'fixture.services', [ [] ], '1.0', 'fixture.bindings' );
has_action( 'init' );
has_filter( 'fixture.services' );
did_action( 'plugins_loaded' );
did_filter( 'fixture.services' );
doing_action( 'plugins_loaded' );
doing_filter( 'fixture.services' );
current_action();
current_filter();
register_uninstall_hook( __FILE__, $callback );
