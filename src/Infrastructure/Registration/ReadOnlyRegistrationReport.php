<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Infrastructure\Registration;

/**
 * A view of a registration log that can only read it.
 *
 * The plugin hands this to services instead of the log itself. Typing a
 * parameter as RegistrationReport does not hide the log's record() method
 * from a service that checks what it received; this object has none.
 */
final class ReadOnlyRegistrationReport implements RegistrationReport {

	/**
	 * Report to read from.
	 */
	private RegistrationReport $report;

	/**
	 * Instantiate a ReadOnlyRegistrationReport object.
	 *
	 * @param RegistrationReport $report Report to read from, usually the
	 *                                   plugin's registration log.
	 */
	public function __construct( RegistrationReport $report ) {
		$this->report = $report;
	}

	/**
	 * Get every record, in the order the decisions were made.
	 *
	 * @return list<RegistrationRecord> Registration records.
	 */
	public function get_records(): array {
		return $this->report->get_records();
	}
}
