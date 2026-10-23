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
 * Creates injectors that start out empty.
 *
 * The scoped lifecycle calls build a separate object graph for each site and
 * each network they visit, so that nothing a service or a shared collaborator
 * read in one site leaks into the next. Each graph starts from an injector
 * created here and is configured by the composition root in that site.
 *
 * A plugin that is given a custom Injector implementation and has scoped
 * lifecycle services also needs a factory that creates that implementation.
 */
interface InjectorFactory {

	/**
	 * Create a new injector with no configuration and no shared instances.
	 *
	 * @return Injector New injector.
	 */
	public function create(): Injector;
}
