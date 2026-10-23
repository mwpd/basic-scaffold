<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;

/**
 * An injector whose configuration methods leave it untouched and return a
 * configured copy instead, as the Injector interface allows.
 */
final class ImmutableInjector implements Injector {

	private SimpleInjector $inner;

	public function __construct( ?SimpleInjector $inner = null ) {
		$this->inner = $inner ?? new SimpleInjector();
	}

	public function make( string $interface_or_class, array $arguments = [] ): object {
		return $this->inner->make( $interface_or_class, $arguments );
	}

	public function bind( string $from, string $to ): Injector {
		$copy = $this->copy();
		$copy->inner->bind( $from, $to );

		return $copy;
	}

	public function bind_argument( string $interface_or_class, string $argument_name, $value ): Injector {
		$copy = $this->copy();
		$copy->inner->bind_argument( $interface_or_class, $argument_name, $value );

		return $copy;
	}

	public function share( string $interface_or_class ): Injector {
		$copy = $this->copy();
		$copy->inner->share( $interface_or_class );

		return $copy;
	}

	public function delegate( string $interface_or_class, callable $delegation ): Injector {
		$copy = $this->copy();
		$copy->inner->delegate( $interface_or_class, $delegation );

		return $copy;
	}

	private function copy(): self {
		return new self( clone $this->inner );
	}
}
