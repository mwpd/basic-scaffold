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

namespace MWPD\BasicScaffold\Tests\Architecture;

/**
 * A throwaway repository on disk, for testing the repository checks.
 *
 * Each instance lives in its own directory below the system's temporary
 * directory, and every class it declares gets a namespace of its own, so
 * repositories from different tests never clash.
 */
final class TemporaryRepository {

	/**
	 * Root of the repository.
	 */
	private string $root;

	/**
	 * Namespace unique to this repository.
	 */
	private string $namespace;

	/**
	 * Instantiate a TemporaryRepository object.
	 */
	public function __construct() {
		$id              = uniqid( 'repository', false );
		$this->root      = sys_get_temp_dir() . '/mwpd-architecture-' . $id;
		$this->namespace = 'Temporary\\' . ucfirst( $id );

		mkdir( $this->root );
		$this->write(
			'composer.json',
			(string) json_encode(
				[
					'require'      => [ 'php' => '^7.4' ],
					'autoload'     => [ 'psr-4' => [ $this->namespace . '\\' => 'src/' ] ],
					'autoload-dev' => [ 'psr-4' => [ $this->namespace . '\\Tests\\' => 'tests/php/' ] ],
				]
			)
		);
		$this->write(
			'phpunit.xml.dist',
			'<phpunit><testsuites><testsuite name="unit">'
			. '<directory suffix="Test.php">tests/php/Unit</directory>'
			. '</testsuite></testsuites></phpunit>'
		);
		$this->write( 'plugin.php', "<?php\n\ndeclare( strict_types=1 );\n" );
	}

	/**
	 * Get the namespace unique to this repository.
	 *
	 * @return string Namespace, without a trailing separator.
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}

	/**
	 * Write a file, replacing `NAMESPACE` in its contents by the
	 * repository's namespace.
	 *
	 * @param string $path     Path relative to the root.
	 * @param string $contents Contents of the file.
	 * @return self The repository.
	 */
	public function write( string $path, string $contents ): self {
		$absolute = $this->root . '/' . $path;

		if ( ! is_dir( dirname( $absolute ) ) ) {
			mkdir( dirname( $absolute ), 0777, true );
		}

		$contents = str_replace( 'NAMESPACE', $this->namespace, $contents );
		file_put_contents( $absolute, $contents );

		return $this;
	}

	/**
	 * Get the layout of the repository.
	 *
	 * @return RepositoryLayout Layout with the main file `plugin.php`, `src/`
	 *                          for production code and `views/` for templates.
	 */
	public function get_layout(): RepositoryLayout {
		return new RepositoryLayout(
			$this->root,
			'plugin.php',
			[ 'src' ],
			'tests',
			[ 'views' ],
			[ 'tests/php/Fixture' ]
		);
	}

	/**
	 * Get the repository checks for the repository.
	 *
	 * @return RepositoryChecks Checks of the repository.
	 */
	public function checks(): RepositoryChecks {
		return new RepositoryChecks( $this->get_layout() );
	}

	/**
	 * Delete the repository.
	 */
	public function delete(): void {
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			if ( $file instanceof \SplFileInfo ) {
				$remove = $file->isDir() ? 'rmdir' : 'unlink';
				$remove( $file->getPathname() );
			}
		}

		rmdir( $this->root );
	}
}
