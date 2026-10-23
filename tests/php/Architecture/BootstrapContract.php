<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package MWPD\BasicScaffold
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey\Functions;

/**
 * Checks the hook API calls made while a bootstrap is included.
 *
 * Brain Monkey must be set up first. Run each include in a separate process:
 * the plugin factory, constants and autoloaders belong to that include alone.
 */
final class BootstrapContract {

	/**
	 * Record calls rather than final hook state, so removing a just-added hook
	 * cannot hide work done at include time. Include queries and deprecated APIs.
	 */
	private const HOOK_FUNCTIONS = [
		'add_action',
		'add_filter',
		'remove_action',
		'remove_filter',
		'remove_all_actions',
		'remove_all_filters',
		'do_action',
		'do_action_ref_array',
		'do_action_deprecated',
		'apply_filters',
		'apply_filters_ref_array',
		'apply_filters_deprecated',
		'has_action',
		'has_filter',
		'did_action',
		'did_filter',
		'doing_action',
		'doing_filter',
		'current_action',
		'current_filter',
		'register_activation_hook',
		'register_deactivation_hook',
		'register_uninstall_hook',
	];

	private string $bootstrap;

	public function __construct( string $bootstrap ) {
		$this->bootstrap = $bootstrap;
	}

	/**
	 * Include the bootstrap without firing its callbacks, then check every call.
	 *
	 * @return array<string> Problems found.
	 */
	public function check(): array {
		/** @var array<array{function: string, arguments: array<mixed>}> $calls */
		$calls = [];
		foreach ( self::HOOK_FUNCTIONS as $function ) {
			Functions\when( $function )->alias(
				/**
				 * @param mixed ...$arguments Hook API arguments.
				 * @return mixed Inert result; callbacks must not run during the include.
				 */
				static function ( ...$arguments ) use ( &$calls, $function ) {
					$calls[] = [
						'function'  => $function,
						'arguments' => $arguments,
					];

					if ( $function === 'apply_filters' ) {
						return $arguments[1] ?? null;
					}
					if ( in_array( $function, [ 'apply_filters_ref_array', 'apply_filters_deprecated' ], true ) ) {
						$values = $arguments[1] ?? [];

						return is_array( $values ) ? ( $values[0] ?? null ) : null;
					}

					if ( in_array( $function, [ 'did_action', 'did_filter' ], true ) ) {
						return 0;
					}

					return in_array( $function, [ 'add_action', 'add_filter' ], true );
				}
			);
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' );
		}
		// Isolate the include's variables from the recorder and its expectations.
		self::include_bootstrap( $this->bootstrap );

		$expected = [
			'add_action'                 => 0,
			'register_activation_hook'   => 0,
			'register_deactivation_hook' => 0,
		];
		$problems = [];
		foreach ( $calls as $call ) {
			$function  = $call['function'];
			$arguments = $call['arguments'];
			$target    = $arguments[0] ?? null;
			$label     = $function . '(' . ( is_string( $target ) ? '"' . $target . '"' : '' ) . ')';
			$allowed   = isset( $expected[ $function ] )
				&& $target === ( $function === 'add_action' ? 'plugins_loaded' : $this->bootstrap )
				&& is_callable( $arguments[1] ?? null );

			if ( ! $allowed ) {
				$problems[] = $this->problem( 'calls ' . $label . ' at include time.' );
				continue;
			}
			++$expected[ $function ];
			if ( $expected[ $function ] > 1 ) {
				$problems[] = $this->problem( 'calls ' . $label . ' more than once at include time.' );
			}
		}
		foreach ( $expected as $function => $count ) {
			if ( $count === 0 ) {
				$problems[] = sprintf(
					'[%s] Bootstrap "%s" does not register %s. Register exactly one plugins_loaded action and the'
					. ' activation and deactivation hooks for this bootstrap, with callable callbacks. %s.',
					RuleManifest::BOOTSTRAP_CONTRACT,
					$this->bootstrap,
					$function === 'add_action' ? 'add_action("plugins_loaded")' : $function . '()',
					RuleManifest::get_explanation( RuleManifest::BOOTSTRAP_CONTRACT )
				);
			}
		}

		return $problems;
	}

	private static function include_bootstrap( string $bootstrap ): void {
		/**
		 * @psalm-suppress UnresolvableInclude The path is only known at runtime.
		 */
		include $bootstrap;
	}

	private function problem( string $problem ): string {
		return sprintf(
			'[%s] Bootstrap "%s" %s Move this call into a service\'s register() or into registration at'
				. ' plugins_loaded. %s.',
			RuleManifest::BOOTSTRAP_CONTRACT,
			$this->bootstrap,
			$problem,
			RuleManifest::get_explanation( RuleManifest::BOOTSTRAP_CONTRACT )
		);
	}
}
