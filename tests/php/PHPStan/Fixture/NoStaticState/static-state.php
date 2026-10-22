<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoStaticState;

final class Registry {

	private const DEFAULTS = [];

	private static ?Registry $instance = null;

	private array $values = self::DEFAULTS;

	public static function instance(): self {
		return new self();
	}

	public function cached(): int {
		static $calls = 0;

		return ++$calls;
	}
}

final class PluginFactory {

	public static function create(): object {
		static $plugin = null;

		if ( $plugin === null ) {
			$plugin = new Registry();
		}

		return $plugin;
	}
}

function counter(): int {
	static $count = 0;

	return ++$count;
}

final class ServiceWithAnonymousClass {

	public function make(): object {
		return new class() {

			private static int $count = 0;

			public function cached(): int {
				static $calls = 0;

				return ++$calls;
			}
		};
	}
}

final class AllowedCache {

	private static array $cache = [];

	private static array $Cache = [];
}
