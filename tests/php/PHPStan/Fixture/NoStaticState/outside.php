<?php

declare( strict_types=1 );

namespace Acme\OtherPlugin;

final class Registry {

	private static ?Registry $instance = null;

	public function cached(): int {
		static $calls = 0;

		return ++$calls;
	}
}
