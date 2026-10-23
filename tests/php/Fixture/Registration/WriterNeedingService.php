<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that asks for the writer of the registration log, which services
 * never receive.
 */
final class WriterNeedingService implements Service {

	public RegistrationLog $log;

	public function __construct( RegistrationLog $log ) {
		$this->log = $log;
	}
}
