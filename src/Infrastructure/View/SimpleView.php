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

namespace MWPD\BasicScaffold\Infrastructure\View;

use MWPD\BasicScaffold\Exception\FailedToEscapeValue;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidContextProperty;
use MWPD\BasicScaffold\Exception\InvalidPath;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\Service\WordPressDebugMode;
use stdClass;
use Throwable;

/**
 * A simplified implementation of a renderable view object.
 *
 * This extends stdClass to get around a deprecation notice in PHP 8.2.
 *
 * @see https://php.watch/versions/8.2/dynamic-properties-deprecated#stdClass
 */
class SimpleView extends stdClass implements View {

	/**
	 * Extension to use for view files.
	 *
	 * @var string
	 */
	protected const VIEW_EXTENSION = 'php';

	/**
	 * Path to the view file to render.
	 */
	protected string $path;

	/**
	 * Internal storage for passed-in context.
	 *
	 * @var array<string, mixed>
	 */
	protected $_context_ = [];

	/**
	 * View factory instance to use.
	 */
	protected ViewFactory $view_factory;

	/**
	 * Debug mode instance to use.
	 */
	private DebugMode $debug_mode;

	/**
	 * Instantiate a SimpleView object.
	 *
	 * @param string      $path         Path to the view file to render.
	 * @param ViewFactory $view_factory View factory instance to use.
	 * @param ?DebugMode  $debug_mode   Debug mode instance to use. Optional, defaults to WordPressDebugMode.
	 * @throws InvalidPath If an invalid Path was passed into the View.
	 */
	public function __construct( string $path, ViewFactory $view_factory, ?DebugMode $debug_mode = null ) {
		$this->path         = $this->validate( $path );
		$this->view_factory = $view_factory;
		$this->debug_mode   = $debug_mode ?? new WordPressDebugMode();
	}

	/**
	 * Render the current view with a given context.
	 *
	 * @param array<string, mixed> $context Context in which to render.
	 *
	 * @return string Rendered HTML, or an empty string if the template failed
	 *                outside of debug mode.
	 * @throws FailedToLoadView If the template failed (in debug mode).
	 */
	public function render( array $context = [] ): string {
		// Add entire context as array to the current instance to pass onto
		// partial views.
		$this->_context_ = $context;

		// Save the current buffering level so the view can return to it
		// afterwards. The template might open or close buffering levels of
		// its own, whether it succeeds or fails.
		$buffer_level = \ob_get_level();
		\ob_start();

		try {
			/**
			 * This include cannot be followed to be statically analyzed.
			 *
			 * @psalm-suppress UnresolvableInclude
			 */
			include $this->path;

			// Buffers the template opened and left open hold part of its
			// output, so they are flushed into the view's own buffer rather
			// than leaked. Flushing runs their output handlers, which can
			// throw, so it stays inside the try. A buffer that cannot be
			// removed stops the loop instead of retrying it forever.
			while ( \ob_get_level() > $buffer_level + 1 && \ob_end_flush() ) {
				continue;
			}
		} catch ( Throwable $throwable ) {
			// Remove whatever levels were added up until now.
			$this->discard_buffers_above( $buffer_level );

			/*
			 * Rendered output goes straight back to WordPress, so this is
			 * where a failure is caught. Debug mode reports it with the
			 * template's own exception as the cause. In production, the view
			 * renders nothing: its part of the page stays empty, and the rest
			 * of the page keeps working. A partial that fails is a view of
			 * its own, so it leaves only its own place empty.
			 */
			if ( $this->is_debug_mode() ) {
				throw FailedToLoadView::from_view_exception(
					$this->path,
					$throwable
				);
			}

			return '';
		}

		// If the template closed the view's own buffer, there is nothing left
		// to collect. Closing the buffer below it would take the caller's
		// output instead.
		if ( \ob_get_level() <= $buffer_level ) {
			return '';
		}

		$buffer = \ob_get_clean();

		return false === $buffer ? '' : $buffer;
	}

	/**
	 * Discard every output buffer above a given level, with its content.
	 *
	 * Removing a buffer runs its output handler, and a handler the failed
	 * template installed can throw as well. PHP removes the buffer anyway, so
	 * the cleanup carries on. The template's own failure is the one that gets
	 * reported; the handler's failure is a consequence of it.
	 *
	 * @param int $level Buffer level to return to.
	 */
	private function discard_buffers_above( int $level ): void {
		while ( \ob_get_level() > $level ) {
			$current_level = \ob_get_level();

			try {
				$removed = $this->remove_buffer();
			} catch ( Throwable $handler_failure ) {
				$removed = \ob_get_level() < $current_level;
			}

			// A buffer that cannot be removed stops the loop instead of
			// retrying it forever.
			if ( ! $removed ) {
				return;
			}
		}
	}

	/**
	 * Remove the innermost output buffer and discard its content.
	 *
	 * @return bool Whether a buffer was removed.
	 * @throws Throwable If the buffer's output handler throws. PHP removes the
	 *                   buffer anyway.
	 */
	private function remove_buffer(): bool {
		return \ob_end_clean();
	}

	/**
	 * Render a partial view.
	 *
	 * This can be used from within a currently rendered view, to include
	 * nested partials.
	 *
	 * The passed-in context is optional, and will fall back to the parent's
	 * context if omitted.
	 *
	 * @param string                    $path    Path of the partial to render.
	 * @param array<string, mixed>|null $context Context in which to render the partial.
	 *
	 * @return string Rendered HTML, or an empty string if the partial failed
	 *                outside of debug mode.
	 * @throws InvalidPath If the provided path was not valid (in debug mode,
	 *                     with the built-in view factories).
	 * @throws FailedToLoadView If the partial failed (in debug mode).
	 */
	public function render_partial( string $path, ?array $context = null ): string {
		return $this->view_factory->create( $path )
									->render( $context ?? $this->_context_ );
	}

	/**
	 * Return the raw value of a context property.
	 *
	 * By default, properties are automatically escaped when accessing them
	 * within the view. This method allows direct access to the raw value
	 * instead to bypass this automatic escaping.
	 *
	 * @param string $property Property for which to return the raw value.
	 * @return mixed Raw context property value.
	 * @throws InvalidContextProperty If the property does not exist (in debug mode).
	 */
	public function raw( $property ) {
		if ( array_key_exists( $property, $this->_context_ ) ) {
			return $this->_context_[ $property ];
		}

		/*
		 * We only throw an exception here if we are in debugging mode, as we
		 * don't want to take the server down when trying to render a missing
		 * property.
		 */
		if ( $this->is_debug_mode() ) {
			throw InvalidContextProperty::from_property( $property );
		}

		return null;
	}

	/**
	 * Validate a path.
	 *
	 * The path is relative to the plugin root, and the file it resolves to
	 * must stay inside it (see ViewLocations).
	 *
	 * @param string $path Path to validate.
	 *
	 * @return string Validated path, in its canonical form.
	 * @throws InvalidPath If an invalid path was passed into the View.
	 */
	protected function validate( string $path ): string {
		$plugin_root = \dirname( __DIR__, 3 );
		$path        = $this->check_extension( $path, static::VIEW_EXTENSION );
		$path        = $this->ensure_trailing_slash( $plugin_root ) . $path;

		if ( ! \is_readable( $path ) ) {
			throw InvalidPath::from_path( $path );
		}

		return ( new ViewLocations( [ $plugin_root ] ) )->confine( $path );
	}

	/**
	 * Check that the path has the correct extension.
	 *
	 * Optionally adds the extension if none was detected.
	 *
	 * @param string $path      Path to check the extension of.
	 * @param string $extension Extension to use.
	 *
	 * @return string Path with correct extension.
	 */
	protected function check_extension( string $path, string $extension ): string {
		$detected_extension = \pathinfo( $path, PATHINFO_EXTENSION );

		if ( $extension !== $detected_extension ) {
			$path .= '.' . $extension;
		}

		return $path;
	}

	/**
	 * Ensure the path has a trailing slash.
	 *
	 * @param string $path Path to maybe add a trailing slash.
	 *
	 * @return string Path with trailing slash.
	 */
	protected function ensure_trailing_slash( string $path ): string {
		return \rtrim( $path, '/\\' ) . '/';
	}

	/**
	 * Return the escaped value of a context property.
	 *
	 * Use the raw() method to skip automatic escaping.
	 *
	 * @param string $property Property for which to return the escaped value.
	 * @return string Escaped context property value.
	 * @throws InvalidContextProperty If the property does not exist (in debug mode).
	 * @throws FailedToEscapeValue If the value cannot be escaped (in debug mode).
	 */
	public function __get( string $property ) {
		if ( array_key_exists( $property, $this->_context_ ) ) {
			$value = $this->_context_[ $property ];

			if ( $this->is_stringable( $value ) ) {
				return $this->escape( $value );
			}

			/*
			 * The property exists, so reporting it as missing would send the
			 * developer looking in the wrong place. Like a missing property,
			 * it only throws in debugging mode and renders nothing otherwise.
			 */
			if ( $this->is_debug_mode() ) {
				throw FailedToEscapeValue::from_value( $value );
			}

			return '';
		}

		/*
		 * We only throw an exception here if we are in debugging mode, as we
		 * don't want to take the server down when trying to render a missing
		 * property.
		 */
		if ( $this->is_debug_mode() ) {
			throw InvalidContextProperty::from_property( $property );
		}

		// Return an empty string if the property does not exist.
		return '';
	}

	/**
	 * Magic isset to enable usage of isset() or empty() on context properties.
	 *
	 * @param string $property Property to check.
	 * @return bool
	 */
	public function __isset( string $property ): bool {
		return isset( $this->_context_[ $property ] );
	}

	/**
	 * Escape a value for output.
	 *
	 * @param mixed $value Value to escape.
	 * @return string Escaped value, or an empty string if the value could not
	 *                be escaped outside of debug mode.
	 * @throws FailedToEscapeValue If the value could not be escaped (in debug mode).
	 */
	protected function escape( $value ): string {
		if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
			$value = (string) $value;
		}

		if ( is_scalar( $value ) ) {
			$value = (string) $value;
		}

		if ( ! is_string( $value ) ) {
			/*
			 * A value without a string form, like an array, is a bug in the
			 * code that passed it in. Debug mode reports it; production renders
			 * nothing in its place instead of breaking the whole view.
			 */
			if ( $this->is_debug_mode() ) {
				throw FailedToEscapeValue::from_value( $value );
			}

			return '';
		}

		// ENT_QUOTES escapes single quotes as well as double quotes, so the
		// value is safe inside an attribute whichever quote character the
		// template uses. ENT_SUBSTITUTE replaces invalid UTF-8 with U+FFFD
		// instead of discarding the whole value.
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Check whether debugging mode is enabled.
	 *
	 * @return bool Whether debugging mode is enabled.
	 */
	protected function is_debug_mode(): bool {
		return $this->debug_mode->is_debug_mode();
	}

	/**
	 * Check if a value is stringable.
	 *
	 * @param mixed $value Value to check.
	 * @return bool Whether the value is stringable.
	 */
	protected function is_stringable( $value ): bool {
		return is_scalar( $value )
			|| ( is_object( $value ) && method_exists( $value, '__toString' ) );
	}
}
