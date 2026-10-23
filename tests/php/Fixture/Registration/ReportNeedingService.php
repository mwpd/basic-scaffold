<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that reads the registration report without observing the
 * registration itself.
 */
final class ReportNeedingService implements Service {

	public RegistrationReport $report;

	public function __construct( RegistrationReport $report ) {
		$this->report = $report;
	}
}
