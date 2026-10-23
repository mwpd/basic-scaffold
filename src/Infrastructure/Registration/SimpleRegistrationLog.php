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
 * Keep the registration records of the current request in memory.
 *
 * This is the log the plugin uses when it was not given one. It lives as long
 * as the request and stores nothing anywhere else.
 */
final class SimpleRegistrationLog implements RegistrationLog {

	/**
	 * Records in the order they were added.
	 *
	 * @var list<RegistrationRecord>
	 */
	private array $records = [];

	/**
	 * Add a record after the ones already kept.
	 *
	 * @param RegistrationRecord $record Record of a decision.
	 */
	public function record( RegistrationRecord $record ): void {
		$this->records[] = $record;
	}

	/**
	 * Get every record, in the order they were added.
	 *
	 * @return list<RegistrationRecord> Registration records.
	 */
	public function get_records(): array {
		return $this->records;
	}
}
