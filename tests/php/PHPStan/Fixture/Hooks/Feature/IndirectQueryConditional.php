<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Conditional;

final class IndirectQueryConditional implements Conditional {

	public static function is_needed(): bool {
		$later = static function (): bool {
			return is_singular();
		};
		$arrow = static fn(): bool => is_singular();

		return self::helper();
	}

	private static function helper(): bool {
		return is_singular();
	}
}
