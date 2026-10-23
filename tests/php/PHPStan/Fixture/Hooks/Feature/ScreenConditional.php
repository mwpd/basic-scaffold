<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Conditional;

final class ScreenConditional implements Conditional {

	public static function is_needed(): bool {
		return get_current_screen() !== null;
	}
}
