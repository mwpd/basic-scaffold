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
 * Something that is delayed to a later point in the execution flow.
 *
 * A class marked as being delayed can return the action at which it requires
 * to be registered.
 *
 * This can be used to only register a given object after certain contextual
 * requirements are met, like registering a frontend rendering service only
 * after the loop has been set up.
 *
 * It also returns the priority at which to register on that action. Most
 * services do not care, and use the DefaultRegistrationPriority trait to
 * register at WordPress's default priority of 10.
 *
 * Both are read before the service is built, so they are static.
 */
interface Delayed {

	/**
	 * Get the action to use for registering the service.
	 *
	 * @return non-empty-string Registration action to use.
	 */
	public static function get_registration_action(): string;

	/**
	 * Get the priority to use for registering the service on its action.
	 *
	 * The priority places the service among the other callbacks of the action,
	 * as for add_action().
	 *
	 * Dependencies take precedence. A service that implements HasDependencies
	 * never registers before its dependencies, so if they are only ready after
	 * this priority, or after the action, has passed, it registers as soon as
	 * they are, at the end of the action they arrive on. A priority that has
	 * passed cannot be honoured after the fact.
	 *
	 * @return int Registration priority to use.
	 */
	public static function get_registration_priority(): int;
}
