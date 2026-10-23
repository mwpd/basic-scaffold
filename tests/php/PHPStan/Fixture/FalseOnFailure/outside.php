<?php

declare( strict_types=1 );

namespace OtherPlugin {

	final class Service {

		/** @return string|false */
		public function value() { return false; }
	}

	/** @return string|false */
	function value() { return false; }

	$closure = /** @return string|false */ static function () { return false; };

	$arrow = /** @return string|false */ static fn() => false;
}

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\FalseOnFailure\Excluded {

	final class Service {

		/** @return string|false */
		public function value() { return false; }
	}

	/** @return string|false */
	function value() { return false; }

	$closure = /** @return string|false */ static function () { return false; };

	$arrow = /** @return string|false */ static fn() => false;
}
