<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold;

use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;

/**
 * This sample service only renders a silly "Hello World" notice in the admin
 * backend.
 *
 * It is meant to illustrate how to hook services into the plugin flow
 * and how to have their dependencies by injected.
 *
 * Note that the dependency here is actually an interface, not a class. We can
 * still just transparently use it though.
 */
final class SampleService implements Service, Registerable {

	/** @var ViewFactory */
	private $view_factory;

	/**
	 * Instantiate a SampleService object.
	 *
	 * @param ViewFactory $view_factory View factory to use for instantiating
	 *                                  the views.
	 */
	public function __construct( ViewFactory $view_factory ) {
		$this->view_factory = $view_factory;
	}

	/**
	 * Register the service.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	/**
	 * Render the admin notice.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		echo $this->view_factory->create( 'views/test-service' )
		                        ->render( [ 'plugin' => 'MWPD Boilerplate' ] );
	}
}
