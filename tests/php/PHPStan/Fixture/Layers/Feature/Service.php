<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Exception\LayerException;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Contract;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Other\Helper;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Plugin;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\TestDouble\Fake;

final class Service {

	private Contract $contract;

	public function __construct( Contract $contract ) {
		$this->contract = $contract;
	}

	/**
	 * @param Concrete $contract Only documented as the concrete class.
	 */
	public function documented( Contract $contract ): void {
		$this->contract = $contract;
	}

	public function help(): Helper {
		return new Helper();
	}

	public function fail(): void {
		throw LayerException::for_reason();
	}

	public function plugin(): string {
		return Plugin::class;
	}

	public function fake(): object {
		return new Fake();
	}

	public function anonymous(): object {
		return new class() {

			public function concrete(): object {
				return new Concrete();
			}
		};
	}
}
