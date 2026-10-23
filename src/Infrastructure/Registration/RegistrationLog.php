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
 * Where the plugin writes down the decisions it makes while registering its
 * services.
 *
 * Only the plugin writes to it. Services receive the RegistrationReport it
 * extends, which can only read, so nothing a service does can change the
 * history it shows. The log keeps the records of the current request only,
 * in memory.
 */
interface RegistrationLog extends RegistrationReport {

	/**
	 * Add a record after the ones already kept. A record never replaces an
	 * earlier one, even for the same service.
	 *
	 * @param RegistrationRecord $record Record of a decision.
	 */
	public function record( RegistrationRecord $record ): void;
}
