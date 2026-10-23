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

use FilesystemIterator;
use MWPD\BasicScaffold\Tests\ViewHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A plugin may move the infrastructure to another directory level, as one
 * downstream plugin did. The views must still resolve against the plugin root
 * that the view configuration names, not against wherever the view classes
 * now live.
 *
 * The relocated copy is loaded in a separate PHP process, so that it is the
 * only copy of the infrastructure classes there.
 */
final class RelocatedInfrastructureTest extends TestCase {

	/**
	 * Script that renders one view through both view implementations.
	 *
	 * Arguments: infrastructure directory, exception directory, plugin root,
	 * view. Prints the SimpleView output, the TemplatedViewFactory output
	 * with default locations, and the file SimpleView was loaded from.
	 */
	private const RENDER_SCRIPT = <<<'PHP'
<?php
declare( strict_types=1 );

use MWPD\BasicScaffold\Infrastructure\View;

[ , $infrastructure, $exceptions, $plugin_root, $view ] = $argv;

spl_autoload_register(
	static function ( string $class_name ) use ( $infrastructure, $exceptions ): void {
		$directories = [
			'MWPD\\BasicScaffold\\Infrastructure\\' => $infrastructure,
			'MWPD\\BasicScaffold\\Exception\\'      => $exceptions,
		];

		foreach ( $directories as $prefix => $directory ) {
			if ( 0 === strpos( $class_name, $prefix ) ) {
				$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
				require $directory . '/' . $relative . '.php';
			}
		}
	}
);

$configuration = new View\ViewConfiguration( $plugin_root, 'basic-scaffold' );
$factory       = new View\SimpleViewFactory( null, $configuration );

echo ( new View\SimpleView( $view, $factory, null, $configuration ) )->render(), "\n";
echo ( new View\TemplatedViewFactory( [], null, $configuration ) )->create( $view )->render(), "\n";
echo ( new ReflectionClass( View\SimpleView::class ) )->getFileName(), "\n";
PHP;

	/**
	 * Temporary repository holding the relocated copy, if any.
	 */
	private ?TemporaryRepository $repository = null;

	protected function tear_down() {
		if ( $this->repository !== null ) {
			$this->repository->delete();
			$this->repository = null;
		}

		parent::tear_down();
	}

	public function test_views_resolve_the_same_files_after_moving_the_infrastructure_deeper(): void {
		$source_root      = (string) realpath( ViewHelper::PLUGIN_ROOT );
		$this->repository = new TemporaryRepository();
		$infrastructure   = 'src/Library/Deeper/Infrastructure';

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_root . '/src/Infrastructure', FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $files as $file ) {
			if ( $file instanceof SplFileInfo && $file->isFile() ) {
				$this->repository->write(
					$infrastructure . substr( $file->getPathname(), strlen( $source_root . '/src/Infrastructure' ) ),
					(string) file_get_contents( $file->getPathname() )
				);
			}
		}

		$this->repository->write( 'render.php', self::RENDER_SCRIPT );
		$relocated = $this->repository->get_root() . '/' . $infrastructure;

		$command = implode(
			' ',
			array_map(
				'escapeshellarg',
				[
					PHP_BINARY,
					$this->repository->get_root() . '/render.php',
					$relocated,
					$source_root . '/src/Exception',
					(string) realpath( ViewHelper::SCOPED_PLUGIN_FOLDER ),
					'views/probe',
				]
			)
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( $command . ' 2>&1', $lines, $exit_code );

		$output = implode( "\n", $lines );
		$this->assertSame( 0, $exit_code, $output );
		$this->assertCount( 3, $lines, $output );

		// The view classes really were loaded from the relocated copy.
		$this->assertStringStartsWith( $relocated, $lines[2] );

		// Both view implementations still find the plugin's own file.
		$this->assertSame( '<p>Probe comes from plugin.</p>', $lines[0] );
		$this->assertSame( '<p>Probe comes from plugin.</p>', $lines[1] );
	}
}
