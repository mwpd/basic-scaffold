<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

/**
 * Callbacks of every shape, for the uninstall callback setting.
 */
final class UninstallBridge {

	public static function uninstall(): void {
		self::clean_up();
	}

	public function not_static(): void {
	}

	private static function clean_up(): void {
	}
}
