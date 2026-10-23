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
 * A service that shows how the plugin registered its services.
 *
 * Recording the registration costs memory and time in every request, so the
 * plugin only does it while a service needs it. Before it schedules any
 * service, it asks each configured class implementing this interface whether
 * it observes the registration in this request. Only when one says yes does
 * the plugin start a RegistrationLog, record every decision from the first
 * one on, and let constructors receive the RegistrationReport. Otherwise it
 * records nothing and creates no log.
 *
 * The question is asked by class, before anything is built, so it is static.
 */
interface ObservesRegistration {

	/**
	 * Check whether the service observes the registration in this request.
	 *
	 * This runs for every request, before any service is registered, so it
	 * must be cheap, and must not load or build anything to find out.
	 *
	 * @return bool Whether the registration is to be recorded.
	 */
	public static function observes_registration(): bool;
}
