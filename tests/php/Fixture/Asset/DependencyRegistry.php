<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use _WP_Dependency;

/**
 * Stands in for WP_Styles or WP_Scripts: keeps the first registration of each
 * handle, as WordPress does, and records what is enqueued.
 */
final class DependencyRegistry {

	/**
	 * @var array<string,_WP_Dependency>
	 */
	public array $registered = [];

	/**
	 * @var list<string>
	 */
	public array $queue = [];

	/**
	 * @return _WP_Dependency|false
	 */
	public function query( string $handle ) {
		return $this->registered[ $handle ] ?? false;
	}

	/**
	 * @param mixed        $src  Source URL, or false.
	 * @param array<mixed> $deps Dependencies.
	 * @param mixed        $ver  Version.
	 * @param mixed        $args Media of a style.
	 */
	public function add( string $handle, $src, array $deps, $ver, $args = null ): bool {
		if ( isset( $this->registered[ $handle ] ) ) {
			return false;
		}

		$this->registered[ $handle ] = new _WP_Dependency( $handle, $src, $deps, $ver, $args );

		return true;
	}

	/**
	 * @param mixed $value Value to store.
	 */
	public function add_data( string $handle, string $key, $value ): void {
		$this->registered[ $handle ]->extra[ $key ] = $value;
	}

	public function enqueue( string $handle ): void {
		$this->queue[] = $handle;
	}
}
