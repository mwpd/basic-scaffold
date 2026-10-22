<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

final class ServiceFactory {

	public static function service_class(): string {
		return GoodService::class;
	}
}
