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

declare(strict_types=1);

namespace MWPD\BasicScaffold\Infrastructure;

/**
 * Interface for services that depend on other services.
 */
interface HasDependencies {

	/**
	 * Get the list of service IDs this service depends on.
	 *
	 * @return string[] List of service IDs.
	 */
	public static function get_dependencies(): array;
}
