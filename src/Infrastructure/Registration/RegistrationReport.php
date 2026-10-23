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
 * Read-only access to how the plugin registered its services in this request.
 *
 * This is what a service receives when its constructor asks for it: the
 * history as it is when it is read, never a way to change it. Reading it
 * builds nothing and asks no condition again; it only returns the records
 * of decisions the plugin already made.
 *
 * The plugin only records the registration while a configured service
 * implementing ObservesRegistration observes it, so a constructor can only
 * receive this in a request where one does.
 */
interface RegistrationReport {

	/**
	 * Get every record of this request, in the order the decisions were made.
	 *
	 * A service that changed state appears once for each state, so the latest
	 * record of a service is its current state.
	 *
	 * @return list<RegistrationRecord> Registration records.
	 */
	public function get_records(): array;
}
