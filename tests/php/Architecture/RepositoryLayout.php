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

use MWPD\BasicScaffold\Tests\PHPStan\Rule\PluginNamespace;
use Nette\Neon\Neon;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use SplFileInfo;

/**
 * Where things live in the plugin's repository.
 *
 * The repository-structure checks read the PSR-4 roots from composer.json and
 * the test suites from phpunit.xml.dist. What those files cannot tell, the
 * constants below declare. A plugin generated from the scaffold adjusts them
 * here when it moves things around.
 */
final class RepositoryLayout {

	/**
	 * The composition root, the class that wires the plugin together.
	 */
	public const COMPOSITION_ROOT = 'MWPD\\BasicScaffold\\BasicScaffoldPlugin';

	/**
	 * Subsystem services built outside get_service_classes(), keyed by class
	 * name, with the factory or other route that builds each one.
	 *
	 * @var array<string, string>
	 */
	public const SERVICES_BUILT_ELSEWHERE = [];

	/**
	 * The plugin's main file, the only one with side effects.
	 */
	public const BOOTSTRAP = 'basic-scaffold.php';

	/**
	 * Directories with the classes that run in production.
	 */
	public const PRODUCTION = [ 'src' ];

	/**
	 * Directories with the view templates that run in production.
	 */
	public const PRODUCTION_VIEWS = [ 'views' ];

	/**
	 * Directory that holds all tests and their support code.
	 */
	public const TESTS = 'tests';

	/**
	 * Directories with view templates for the tests.
	 */
	public const TEST_VIEWS = [ 'tests/php/Fixture/views' ];

	/**
	 * Directories with fixtures for the architecture rules, which break the
	 * rules on purpose, and with stand-ins for WordPress classes, which live in
	 * the global namespace like the classes they stand in for.
	 */
	public const RULE_FIXTURES = [ 'tests/php/PHPStan/Fixture', 'tests/php/Fixture/WordPress' ];

	/**
	 * Root of the repository, without a trailing slash.
	 */
	private string $root;

	/**
	 * Path of the main file, relative to the root.
	 */
	private string $bootstrap;

	/**
	 * Production directories, relative to the root.
	 *
	 * @var array<string>
	 */
	private array $production;

	/**
	 * Production view template directories, relative to the root.
	 *
	 * @var array<string>
	 */
	private array $production_views;

	/**
	 * Tests directory, relative to the root.
	 */
	private string $tests;

	/**
	 * View template directories, relative to the root.
	 *
	 * @var array<string>
	 */
	private array $view_templates;

	/**
	 * Rule fixture directories, relative to the root.
	 *
	 * @var array<string>
	 */
	private array $rule_fixtures;

	/**
	 * Subsystem service classes and the other routes that build them.
	 *
	 * @var array<string, string>
	 */
	private array $services_built_elsewhere;

	/**
	 * Instantiate a RepositoryLayout object.
	 *
	 * @param string        $root             Root of the repository.
	 * @param string        $bootstrap        Main file, relative to the root.
	 * @param array<string> $production       Production class directories.
	 * @param array<string> $production_views Production view directories.
	 * @param string        $tests            Tests directory.
	 * @param array<string> $test_views       View directories of the tests.
	 * @param array<string> $rule_fixtures    Rule fixture directories.
	 * @param array<string, string> $services_built_elsewhere Classes built by other routes.
	 */
	public function __construct(
		string $root,
		string $bootstrap,
		array $production,
		array $production_views,
		string $tests,
		array $test_views,
		array $rule_fixtures,
		array $services_built_elsewhere = []
	) {
		$this->root                     = rtrim( $root, '/' );
		$this->bootstrap                = $bootstrap;
		$this->production               = $production;
		$this->production_views         = $production_views;
		$this->tests                    = $tests;
		$this->view_templates           = array_merge( $production_views, $test_views );
		$this->rule_fixtures            = $rule_fixtures;
		$this->services_built_elsewhere = $services_built_elsewhere;
	}

	/**
	 * Get the layout of this plugin's repository.
	 *
	 * @return self Layout of the repository.
	 */
	public static function of_plugin(): self {
		return new self(
			dirname( __DIR__, 3 ),
			self::BOOTSTRAP,
			self::PRODUCTION,
			self::PRODUCTION_VIEWS,
			self::TESTS,
			self::TEST_VIEWS,
			self::RULE_FIXTURES,
			self::SERVICES_BUILT_ELSEWHERE
		);
	}

	/**
	 * Get the absolute path of the main file.
	 *
	 * @return string Path of the main file.
	 */
	public function get_bootstrap(): string {
		return $this->absolute( $this->bootstrap );
	}

	/**
	 * Get the files and directories whose code runs in production.
	 *
	 * @return list<string> Absolute paths that exist.
	 */
	public function get_runtime_paths(): array {
		$paths = array_map(
			[ $this, 'absolute' ],
			array_merge( [ $this->bootstrap ], $this->production, $this->production_views )
		);

		return array_values( array_filter( $paths, 'file_exists' ) );
	}

	/**
	 * Get the absolute path of the PHPStan configuration.
	 *
	 * @return string Path of phpstan.neon.dist.
	 */
	public function get_phpstan_config(): string {
		return $this->absolute( 'phpstan.neon.dist' );
	}

	/**
	 * Get the absolute path of composer.json.
	 *
	 * @return string Path of composer.json.
	 */
	public function get_composer_json(): string {
		return $this->absolute( 'composer.json' );
	}

	/**
	 * Get the PSR-4 roots of the production code and the tests.
	 *
	 * @return array<int, array{0: string, 1: string}> Namespace prefix, with a
	 *                                                 trailing separator, and
	 *                                                 absolute directory.
	 */
	public function get_psr4_roots(): array {
		$composer = $this->read_composer_json();
		$roots    = [];

		foreach ( [ 'autoload', 'autoload-dev' ] as $section ) {
			$autoload = $composer[ $section ] ?? [];
			$psr4     = is_array( $autoload ) ? ( $autoload['psr-4'] ?? [] ) : [];
			foreach ( is_array( $psr4 ) ? $psr4 : [] as $prefix => $directories ) {
				// A prefix maps to one directory or to a list of them.
				foreach ( (array) $directories as $directory ) {
					if ( is_string( $directory ) ) {
						$roots[] = [ (string) $prefix, $this->absolute( rtrim( $directory, '/' ) ) ];
					}
				}
			}
		}

		return $roots;
	}

	/**
	 * Get the packages composer.json requires at runtime.
	 *
	 * @return array<string> Package names.
	 */
	public function get_runtime_requirements(): array {
		$require = $this->read_composer_json()['require'] ?? [];

		return is_array( $require ) ? array_map( 'strval', array_keys( $require ) ) : [];
	}

	/**
	 * Get the directories PHPUnit discovers tests in.
	 *
	 * @return array<int, array{directory: string, suffix: string, excluded: array<string>}> Absolute
	 *         directory, file suffix and absolute excluded paths of each suite directory.
	 */
	public function get_suite_directories(): array {
		$path = $this->absolute( 'phpunit.xml.dist' );
		if ( ! is_readable( $path ) ) {
			return [];
		}

		$directories = [];
		$config      = new SimpleXMLElement( (string) file_get_contents( $path ) );
		$suites      = $config->xpath( '//testsuite' );
		foreach ( is_array( $suites ) ? $suites : [] as $suite ) {
			$excluded = [];
			foreach ( $suite->exclude as $exclude ) {
				$excluded[] = $this->absolute( rtrim( (string) $exclude, '/' ) );
			}

			foreach ( $suite->directory as $directory ) {
				$directories[] = [
					'directory' => $this->absolute( rtrim( (string) $directory, '/' ) ),
					'suffix'    => (string) ( $directory['suffix'] ?? 'Test.php' ),
					'excluded'  => $excluded,
				];
			}
		}

		return $directories;
	}

	/**
	 * Get the PHP files in the production directories.
	 *
	 * @return array<string> Absolute paths.
	 */
	public function get_production_files(): array {
		$files = [];
		foreach ( $this->production as $directory ) {
			$files = array_merge( $files, $this->find_php_files( $this->absolute( $directory ) ) );
		}

		return $files;
	}

	/**
	 * Get named declarations in feature subsystems, following the same
	 * namespace configuration as the PHPStan rules.
	 *
	 * @return array<string> Fully qualified class, interface and trait names.
	 */
	public function get_subsystem_declarations(): array {
		/**
		 * Neon::decodeFile() declares a native mixed return type, which Psalm
		 * reads as a reserved word when it targets PHP 7.4.
		 *
		 * @psalm-suppress ReservedWord
		 */
		$config = Neon::decodeFile( $this->get_phpstan_config() );
		assert( is_array( $config ) && is_array( $config['parameters'] ?? null ) );
		$settings = $config['parameters']['architecture'] ?? null;
		assert( is_array( $settings ) );
		$root = $settings['namespace'] ?? null;
		assert( is_string( $root ) );
		$direction  = $settings['dependencyDirection'] ?? null;
		$exceptions = $settings['exceptions'] ?? null;
		assert( is_array( $direction ) && is_string( $direction['infrastructure'] ?? null ) );
		assert( is_array( $exceptions ) && is_string( $exceptions['namespace'] ?? null ) );
		$excluded = $settings['excludedNamespaces'] ?? [];
		assert( is_array( $excluded ) );
		$namespace = new PluginNamespace(
			$root,
			array_merge(
				array_values( array_filter( $excluded, 'is_string' ) ),
				[ $direction['infrastructure'], $exceptions['namespace'] ]
			)
		);
		$classes   = [];
		foreach ( $this->get_production_files() as $path ) {
			$file = PhpFile::from_path( $path );
			if ( PluginNamespace::normalize( $file->get_namespace() ) === PluginNamespace::normalize( $root )
				|| $this->is_rule_fixture( $path ) || $this->is_view_template( $path ) ) {
				continue;
			}
			foreach ( $file->get_declarations() as $declaration ) {
				if ( $namespace->contains( $declaration['name'] ) ) {
					$classes[] = $declaration['name'];
				}
			}
		}

		return $classes;
	}

	/**
	 * Get subsystem service classes built by a factory or another route.
	 *
	 * @return array<string, string> Classes and the routes that build them.
	 */
	public function get_services_built_elsewhere(): array {
		return $this->services_built_elsewhere;
	}

	/**
	 * Get the PHP files below the PSR-4 roots.
	 *
	 * @return array<string> Absolute paths.
	 */
	public function get_class_root_files(): array {
		$files = [];
		foreach ( $this->get_psr4_roots() as [ , $directory ] ) {
			$files = array_merge( $files, $this->find_php_files( $directory ) );
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Get the PHP files in the tests directory.
	 *
	 * @return array<string> Absolute paths.
	 */
	public function get_test_files(): array {
		return $this->find_php_files( $this->absolute( $this->tests ) );
	}

	/**
	 * Get the PHP files in the view template directories.
	 *
	 * @return array<string> Absolute paths.
	 */
	public function get_view_template_files(): array {
		$files = [];
		foreach ( $this->view_templates as $directory ) {
			$files = array_merge( $files, $this->find_php_files( $this->absolute( $directory ) ) );
		}

		return $files;
	}

	/**
	 * Check whether a file is a view template.
	 *
	 * @param string $path Absolute path.
	 * @return bool Whether it is a view template.
	 */
	public function is_view_template( string $path ): bool {
		return $this->is_inside( $path, $this->view_templates );
	}

	/**
	 * Check whether a file is a fixture of the architecture rules.
	 *
	 * @param string $path Absolute path.
	 * @return bool Whether it is a rule fixture.
	 */
	public function is_rule_fixture( string $path ): bool {
		return $this->is_inside( $path, $this->rule_fixtures );
	}

	/**
	 * Get a path relative to the root, for messages.
	 *
	 * @param string $path Absolute path.
	 * @return string Path relative to the root.
	 */
	public function relative( string $path ): string {
		return strpos( $path, $this->root . '/' ) === 0 ? substr( $path, strlen( $this->root ) + 1 ) : $path;
	}

	/**
	 * Get the absolute path of a path relative to the root.
	 *
	 * @param string $path Path relative to the root.
	 * @return string Absolute path.
	 */
	private function absolute( string $path ): string {
		return $this->root . '/' . ltrim( $path, '/' );
	}

	/**
	 * Check whether a file is inside one of a list of directories.
	 *
	 * @param string        $path        Absolute path.
	 * @param array<string> $directories Directories relative to the root.
	 * @return bool Whether the file is inside one of them.
	 */
	private function is_inside( string $path, array $directories ): bool {
		foreach ( $directories as $directory ) {
			if ( strpos( $path, rtrim( $this->absolute( $directory ), '/' ) . '/' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the PHP files below a directory.
	 *
	 * @param string $directory Absolute directory.
	 * @return array<string> Absolute paths, sorted.
	 */
	private function find_php_files( string $directory ): array {
		if ( ! is_dir( $directory ) ) {
			return [];
		}

		$files = [];
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			if ( $file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Read composer.json.
	 *
	 * @return array<mixed> Decoded composer.json.
	 */
	private function read_composer_json(): array {
		$contents = (string) file_get_contents( $this->get_composer_json() );
		$composer = json_decode( $contents, true );

		return is_array( $composer ) ? $composer : [];
	}
}
