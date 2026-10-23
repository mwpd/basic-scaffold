<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package MWPD\BasicScaffold
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey\Container;
use Brain\Monkey\Functions;
use ReflectionProperty;

/**
 * Hook state, fired hook history and WordPress write state.
 *
 * Writes model stored values, not call counts: setting the same value twice
 * is safe, while appending another meta row is not. Hook entries use WordPress's
 * hook name, priority and callback identity, so identical wiring is safe too.
 */
final class LifecycleEffects {

	/**
	 * Stored option, transient and meta values, keyed by their diagnostic.
	 *
	 * @var array<string,array<mixed>>
	 */
	private array $writes = [];

	/**
	 * Hook changes Brain Monkey's storage does not retain.
	 *
	 * @var array<string,int>
	 */
	private array $calls = [];

	/**
	 * Accepted argument counts keyed by hook name, priority and callback identity.
	 * Actions and filters share a namespace in WordPress.
	 *
	 * @var array<string,array<int,array<string,int>>>
	 */
	private array $hooks = [];

	/**
	 * The API first used to add a hook, for diagnostics only.
	 *
	 * @var array<string,string>
	 */
	private array $hook_types = [];

	/**
	 * Retain callbacks so PHP cannot reuse an object's hash during the check.
	 *
	 * @var array<string,string|object|array{0:object|string,1:string}>
	 */
	private array $callbacks = [];

	public function watch(): void {
		foreach ( [ 'action', 'filter' ] as $type ) {
			Functions\when( 'add_' . $type )->alias(
				/** @param string|object|array{0:object|string,1:string} $callback */
				function ( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ) use ( $type ): bool {
					$identity                                       = self::callback_identity( $callback );
					$this->hooks[ $hook ][ $priority ][ $identity ] = $accepted_args;
					$this->callbacks[ $identity ]                   = $this->callbacks[ $identity ] ?? $callback;
					$this->hook_types[ $hook ]                      = $this->hook_types[ $hook ] ?? $type;

					return true;
				}
			);
			Functions\when( 'remove_' . $type )->alias(
				/** @param string|object|array{0:object|string,1:string} $callback */
				function ( string $hook, $callback, int $priority = 10 ): bool {
					$identity = self::callback_identity( $callback );
					$exists   = isset( $this->hooks[ $hook ][ $priority ][ $identity ] );
					unset( $this->hooks[ $hook ][ $priority ][ $identity ] );
					if ( ( $this->hooks[ $hook ][ $priority ] ?? [] ) === [] ) {
						unset( $this->hooks[ $hook ][ $priority ] );
					}
					if ( ( $this->hooks[ $hook ] ?? [] ) === [] ) {
						unset( $this->hooks[ $hook ] );
					}

					return $exists;
				}
			);
		}

		foreach ( [ 'activation', 'deactivation' ] as $type ) {
			Functions\when( 'register_' . $type . '_hook' )->alias(
				function ( string $file ) use ( $type ): void {
					$effect                 = sprintf( 'registers the %s hook for "%s" again', $type, $file );
					$this->calls[ $effect ] = ( $this->calls[ $effect ] ?? 0 ) + 1;
				}
			);
		}

		Functions\when( 'add_option' )->alias(
			/** @param mixed $value */
			function ( string $name, $value = '' ): bool {
				$key = sprintf( 'changes the option "%s" again', $name );
				if ( array_key_exists( $key, $this->writes ) ) {
					return false;
				}

				$this->writes[ $key ] = [ $value ];

				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			/** @param mixed $value */
			function ( string $name, $value ): bool {
				$this->writes[ sprintf( 'changes the option "%s" again', $name ) ] = [ $value ];

				return true;
			}
		);
		Functions\when( 'set_transient' )->alias(
			/** @param mixed $value */
			function ( string $name, $value, int $expiration = 0 ): bool {
				$this->writes[ sprintf( 'changes the transient "%s" again', $name ) ] = [ $value, $expiration ];

				return true;
			}
		);
		foreach ( [ 'option', 'transient' ] as $type ) {
			Functions\when( 'delete_' . $type )->alias(
				function ( string $name ) use ( $type ): bool {
					$key    = sprintf( 'changes the %s "%s" again', $type, $name );
					$exists = array_key_exists( $key, $this->writes );
					unset( $this->writes[ $key ] );

					return $exists;
				}
			);
		}

		foreach ( [ 'post', 'user', 'term', 'comment' ] as $type ) {
			$this->watch_meta( $type );
		}
	}

	/**
	 * Use the callback identity WordPress's _wp_filter_build_unique_id() builds.
	 *
	 * @param string|object|array{0:object|string,1:string} $callback Hook callback.
	 */
	private static function callback_identity( $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_object( $callback ) ) {
			return spl_object_hash( $callback );
		}

		return is_object( $callback[0] )
			? spl_object_hash( $callback[0] ) . $callback[1]
			: $callback[0] . '::' . $callback[1];
	}

	private function watch_meta( string $type ): void {
		Functions\when( 'add_' . $type . '_meta' )->alias(
			/** @param mixed $value */
			function ( int $id, string $name, $value, bool $unique = false ) use ( $type ) {
				$key = sprintf( 'changes the %s meta "%s" for object %d again', $type, $name, $id );
				if ( $unique && isset( $this->writes[ $key ] ) ) {
					return false;
				}

				$this->writes[ $key ][] = $value;

				return 1;
			}
		);
		Functions\when( 'update_' . $type . '_meta' )->alias(
			/**
			 * @param mixed $value
			 * @param mixed $previous
			 */
			function ( int $id, string $name, $value, $previous = '' ) use ( $type ): bool {
				$key  = sprintf( 'changes the %s meta "%s" for object %d again', $type, $name, $id );
				$rows = $this->writes[ $key ] ?? [ $value ];
				foreach ( $rows as $index => $row ) {
					if ( $previous === '' || $row === $previous ) {
						$rows[ $index ] = $value;
					}
				}

				$this->writes[ $key ] = $rows;

				return true;
			}
		);
		Functions\when( 'delete_' . $type . '_meta' )->alias(
			/** @param mixed $value */
			function ( int $id, string $name, $value = '' ) use ( $type ): bool {
				$key       = sprintf( 'changes the %s meta "%s" for object %d again', $type, $name, $id );
				$rows      = $this->writes[ $key ] ?? [];
				$remaining = $value === '' ? [] : array_values(
					array_filter( $rows, static fn( $row ): bool => $row !== $value )
				);
				if ( $remaining === [] ) {
					unset( $this->writes[ $key ] );
				} else {
					$this->writes[ $key ] = $remaining;
				}

				return $rows !== $remaining;
			}
		);
	}

	/**
	 * Snapshot state with a description for each effect that could change.
	 *
	 * @return array<string,mixed> Recorded state.
	 */
	public function snapshot(): array {
		$storage  = Container::instance()->hookStorage();
		$property = new ReflectionProperty( $storage, 'storage' );
		$property->setAccessible( true );
		/** @var array<string,array<string,array<string,array<mixed>>>> $hooks */
		$hooks = $property->getValue( $storage );
		$state = array_merge( $this->writes, $this->calls );

		foreach ( $this->hooks as $name => $priorities ) {
			// Compare sets independently of the order entries were added in.
			ksort( $priorities );
			foreach ( $priorities as &$callbacks ) {
				ksort( $callbacks );
			}
			unset( $callbacks );
			$effect           = sprintf( 'changes the callbacks on the %s "%s"', $this->hook_types[ $name ], $name );
			$state[ $effect ] = $priorities;
		}

		foreach ( $hooks as $operation => $types ) {
			if ( $operation !== 'done' ) {
				continue;
			}
			foreach ( $types as $type => $names ) {
				foreach ( $names as $name => $arguments ) {
					$effect           = sprintf(
						'fires the %s "%s" again',
						$type === 'actions' ? 'action' : 'filter',
						$name
					);
					$state[ $effect ] = $arguments;
				}
			}
		}

		return $state;
	}

	/**
	 * @param array<string,mixed> $before State after the first run.
	 * @return array<string> Effects changed by the second run.
	 */
	public function changes_since( array $before ): array {
		$after   = $this->snapshot();
		$changed = [];
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $effect ) {
			if ( ( $before[ $effect ] ?? null ) !== ( $after[ $effect ] ?? null ) ) {
				$changed[] = $effect;
			}
		}

		return $changed;
	}
}
