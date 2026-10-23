<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestEarlyDelayedDependentService;

/**
 * The priority is read statically, so none of these build a service.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority
 */
final class DefaultRegistrationPriorityTest extends TestCase {

	public function test_it_supplies_the_default_priority_of_10(): void {
		$this->assertContains( DefaultRegistrationPriority::class, class_uses( TestDelayedService::class ) );
		$this->assertSame( 10, TestDelayedService::get_registration_priority() );
	}

	public function test_a_consuming_class_can_override_it(): void {
		$this->assertContains(
			DefaultRegistrationPriority::class,
			class_uses( TestEarlyDelayedDependentService::class )
		);
		$this->assertSame( 5, TestEarlyDelayedDependentService::get_registration_priority() );
	}
}
