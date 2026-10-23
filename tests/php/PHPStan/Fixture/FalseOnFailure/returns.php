<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\FalseOnFailure;

final class Service {

	/** @return string|false */
	public function value() {
		return false;
	}

	/** @phpstan-return int|false */
	public function count() {
		return false;
	}

	/** @psalm-return array<string>|false */
	public function values() {
		return false;
	}

	public function answer(): bool {
		return false;
	}

	public function optional(): ?string {
		return null;
	}

	public function text(): string {
		return 'value';
	}

	/** @return false */
	public function negative(): bool {
		return false;
	}

	/** @return array<string|false> */
	public function nested(): array {
		return [ false ];
	}

	public function inferred() {
		return rand( 0, 1 ) ? 'value' : false;
	}
}

/** @return string|false */
function value() {
	return false;
}

$closure =
	/** @return string|false */
	static function () {
		return false;
	};

$phpstan_closure =
	/** @phpstan-return string|false */
	static function () {
		return false;
	};

$psalm_closure =
	/** @psalm-return string|false */
	static function () {
		return false;
	};

$arrow =
	/** @return string|false */
	static fn() => false;

$phpstan_arrow =
	/** @phpstan-return string|false */
	static fn() => false;

$psalm_arrow =
	/** @psalm-return string|false */
	static fn() => false;

$answer   = static function (): bool { return false; };
$optional = static function (): ?string { return null; };
$text     = static function (): string { return 'value'; };
$inferred = static function () { return rand( 0, 1 ) ? 'value' : false; };

$arrow_answer   = static fn(): bool => false;
$arrow_optional = static fn(): ?string => null;
$arrow_text     = static fn(): string => 'value';
$arrow_inferred = static fn() => rand( 0, 1 ) ? 'value' : false;

/**
 * @return string|false
 * @phpstan-return string
 */
function overridden() {
	return 'value';
}

/** @phpstan-return int|false */
function phpstan_value() {
	return false;
}

/** @psalm-return int|false */
function psalm_value() {
	return false;
}

/** @return false|null */
function null_or_false() {
	return null;
}

function answer(): bool { return false; }
function optional(): ?string { return null; }
function text(): string { return 'value'; }

/** @return false */
function negative(): bool { return false; }
