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

namespace MWPD\BasicScaffold\Infrastructure\Injector;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\InjectorFactory;

/**
 * Creates empty SimpleInjector instances.
 *
 * This is the factory the plugin uses when it is given neither an injector
 * nor an injector factory, matching its default SimpleInjector.
 */
final class SimpleInjectorFactory implements InjectorFactory {

	/**
	 * Create a new injector with no configuration and no shared instances.
	 *
	 * @return Injector New SimpleInjector.
	 */
	public function create(): Injector {
		return new SimpleInjector();
	}
}
