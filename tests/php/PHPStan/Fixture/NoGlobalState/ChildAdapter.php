<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState;

final class ChildAdapter extends InputAdapter {
	public function input(): array {
		return $_GET;
	}
}
