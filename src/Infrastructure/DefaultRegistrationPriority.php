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

namespace MWPD\BasicScaffold\Infrastructure;

/**
 * The registration priority of a Delayed service that has no reason to choose
 * one.
 *
 * It is WordPress's own default priority for add_action(), so the service is
 * registered among the callbacks of its action that did not choose one
 * either. A service that needs a different place implements
 * get_registration_priority() itself, with or without this trait.
 *
 * The name describes the behaviour it supplies. It is the one approved
 * exception to the rule against "Default…" names, see principle 8 in
 * docs/design-principles.md.
 */
trait DefaultRegistrationPriority {

	/**
	 * Get the priority to use for registering the service on its action.
	 *
	 * @return int Registration priority to use.
	 */
	public static function get_registration_priority(): int {
		return 10;
	}
}
