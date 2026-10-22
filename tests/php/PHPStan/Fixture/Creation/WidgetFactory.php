<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

final class WidgetFactory {

	public function make(): Service {
		return new Service();
	}
}
