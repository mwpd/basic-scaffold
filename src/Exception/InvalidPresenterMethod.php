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

namespace MWPD\BasicScaffold\Exception;

use BadMethodCallException;

/**
 * Exception for a method a template calls on `$this` that no presenter
 * offers.
 */
final class InvalidPresenterMethod extends BadMethodCallException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a template that calls a
	 * method while no presenter was passed to the view.
	 *
	 * @param string $method Name of the method the template called.
	 */
	public static function for_missing_presenter( string $method ): self {
		$message = \sprintf(
			'The template called the method "%s()", but the view was rendered without a presenter.'
			. ' Pass one to render() or render_partial().',
			$method
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a template that calls a
	 * method its presenter does not declare.
	 *
	 * @param string $method    Name of the method the template called.
	 * @param string $presenter Class of the presenter the view was rendered with.
	 */
	public static function from_method( string $method, string $presenter ): self {
		$message = \sprintf(
			'The template called the method "%1$s()", which no interface extending Presenter declares'
			. ' for the presenter "%2$s". Declare it on the presenter\'s interface.',
			$method,
			$presenter
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a presenter that declares a
	 * method templates cannot reach, because the template scope has a method
	 * of the same name.
	 *
	 * @param string $method    Name of the method the presenter declares.
	 * @param string $presenter Class of the presenter the view was rendered with.
	 */
	public static function for_reserved_name( string $method, string $presenter ): self {
		$message = \sprintf(
			'The presenter "%1$s" declares the method "%2$s()", which templates cannot call:'
			. ' the template scope has a method of that name. Rename the presenter method.',
			$presenter,
			$method
		);

		return new self( $message );
	}
}
