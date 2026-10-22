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

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Checks of the repository's structure that look at files, not code.
 *
 * Each check returns one message per problem it finds, in the same three
 * parts as every architecture failure: what is wrong and where, what to do
 * instead, and why.
 */
final class RepositoryChecks {

	/**
	 * Layout of the repository to check.
	 */
	private RepositoryLayout $layout;

	/**
	 * Instantiate a RepositoryChecks object.
	 *
	 * @param RepositoryLayout $layout Layout of the repository to check.
	 */
	public function __construct( RepositoryLayout $layout ) {
		$this->layout = $layout;
	}

	/**
	 * The main file and every class file declare strict types, and view
	 * templates declare nothing.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_strict_types(): array {
		$problems = [];
		$files    = array_merge( [ $this->layout->get_bootstrap() ], $this->layout->get_class_root_files() );

		foreach ( $files as $path ) {
			if ( $this->layout->is_view_template( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$file = PhpFile::from_path( $path );
			if ( ( $path === $this->layout->get_bootstrap() || $file->get_declarations() !== [] )
				&& ! $file->declares_strict_types() ) {
				$problems[] = $this->problem(
					RuleManifest::STRICT_TYPES,
					sprintf(
						'%s does not declare strict types. Add declare( strict_types=1 ); right after the file'
						. ' header, so scalar types are enforced instead of silently converted.',
						$this->layout->relative( $path )
					)
				);
			}
		}

		foreach ( $this->layout->get_view_template_files() as $path ) {
			if ( PhpFile::from_path( $path )->declares_anything() ) {
				$problems[] = $this->problem(
					RuleManifest::STRICT_TYPES,
					sprintf(
						'%s is a view template but declares a class, interface, trait or function. Keep'
						. ' templates to markup and output; move the declaration to its own class file.',
						$this->layout->relative( $path )
					)
				);
			}
		}

		return $problems;
	}

	/**
	 * Every class file declares exactly one class, interface or trait, named
	 * after its path.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_declarations_follow_psr4(): array {
		$problems = [];

		foreach ( $this->layout->get_psr4_roots() as $prefix => $directory ) {
			foreach ( $this->layout->get_class_root_files() as $path ) {
				if ( strpos( $path, $directory . '/' ) !== 0
					|| $this->layout->is_view_template( $path )
					|| $this->layout->is_rule_fixture( $path )
					|| $this->is_in_nested_root( $path, $directory ) ) {
					continue;
				}

				$expected     = $prefix . str_replace( '/', '\\', substr( $path, strlen( $directory ) + 1, -4 ) );
				$declarations = PhpFile::from_path( $path )->get_declarations();
				$relative     = $this->layout->relative( $path );

				if ( count( $declarations ) !== 1 ) {
					$problems[] = $this->problem(
						RuleManifest::DECLARATIONS_FOLLOW_PSR4,
						sprintf(
							'%1$s declares %2$d classes, interfaces or traits instead of one. Give %3$s a file of'
							. ' its own, so both Composer and the bundled autoloader can find it.',
							$relative,
							count( $declarations ),
							$expected
						)
					);
					continue;
				}

				if ( strtolower( $declarations[0]['name'] ) !== strtolower( $expected ) ) {
					$problems[] = $this->problem(
						RuleManifest::DECLARATIONS_FOLLOW_PSR4,
						sprintf(
							'%1$s declares %2$s, but its path makes the autoloaders look for %3$s. Rename the'
							. ' class or move the file, so both Composer and the bundled autoloader find it.',
							$relative,
							$declarations[0]['name'],
							$expected
						)
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Including a production file only declares things.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_no_side_effects_on_include(): array {
		$problems = [];

		foreach ( $this->layout->get_production_files() as $path ) {
			foreach ( PhpFile::from_path( $path )->get_side_effects() as $side_effect ) {
				$problems[] = $this->problem(
					RuleManifest::NO_SIDE_EFFECTS_ON_INCLUDE,
					sprintf(
						'%1$s:%2$d runs code when the file is included (%3$s). Only the main plugin file has'
						. ' side effects; move this into a method of a service, called from register(), or'
						. ' into the main plugin file.',
						$this->layout->relative( $path ),
						$side_effect['line'],
						$side_effect['code']
					)
				);
			}
		}

		return $problems;
	}

	/**
	 * composer.json requires nothing at runtime but PHP.
	 *
	 * Whether production code uses a development package is checked with
	 * composer-dependency-analyser, see RuntimeDependenciesTest.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_runtime_requirements(): array {
		$problems = [];

		foreach ( $this->layout->get_runtime_requirements() as $package ) {
			if ( $package === 'php' ) {
				continue;
			}

			$problems[] = $this->problem(
				RuleManifest::NO_RUNTIME_DEPENDENCIES,
				sprintf(
					'composer.json requires %s at runtime. The plugin runs on PHP and WordPress alone; move the'
					. ' package to require-dev if only development needs it, or bundle the code the plugin'
					. ' needs into src/.',
					$package
				)
			);
		}

		return $problems;
	}

	/**
	 * PHPUnit discovers every test.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_tests_are_discovered(): array {
		$problems = [];
		$suites   = $this->layout->get_suite_directories();

		foreach ( $this->layout->get_test_files() as $path ) {
			if ( $this->layout->is_view_template( $path ) || $this->layout->is_rule_fixture( $path ) ) {
				continue;
			}

			$relative = $this->layout->relative( $path );

			if ( substr( $path, -8 ) === 'Test.php' && ! $this->is_discovered( $path, $suites ) ) {
				$problems[] = $this->problem(
					RuleManifest::TESTS_ARE_DISCOVERED,
					sprintf(
						'%1$s looks like a test, but no PHPUnit suite in phpunit.xml.dist includes it, so it never'
						. ' runs. Move it into one of the suite directories (%2$s).',
						$relative,
						$this->describe_suites( $suites )
					)
				);
				continue;
			}

			foreach ( PhpFile::from_path( $path )->get_declarations() as $declaration ) {
				if ( $this->is_discovered( $path, $suites )
					|| ! $this->is_concrete_test_case( $declaration['name'], $path ) ) {
					continue;
				}

				$problems[] = $this->problem(
					RuleManifest::TESTS_ARE_DISCOVERED,
					sprintf(
						'%1$s is a test case, but PHPUnit never runs it from %2$s. Name the file after the class'
						. ' with a Test.php suffix, inside one of the suite directories (%3$s).',
						$declaration['name'],
						$relative,
						$this->describe_suites( $suites )
					)
				);
			}
		}

		return $problems;
	}

	/**
	 * Check whether a file belongs to a PSR-4 root nested inside another one.
	 *
	 * @param string $path      Absolute path of the file.
	 * @param string $directory Absolute directory of the root being checked.
	 * @return bool Whether a more specific root owns the file.
	 */
	private function is_in_nested_root( string $path, string $directory ): bool {
		foreach ( $this->layout->get_psr4_roots() as $other ) {
			if ( $other !== $directory
				&& strpos( $other, $directory . '/' ) === 0
				&& strpos( $path, $other . '/' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether PHPUnit discovers a file.
	 *
	 * @param string                $path   Absolute path of the file.
	 * @param array<string, string> $suites Suffix of each suite, keyed by
	 *                                      directory.
	 * @return bool Whether a suite includes the file.
	 */
	private function is_discovered( string $path, array $suites ): bool {
		foreach ( $suites as $directory => $suffix ) {
			if ( strpos( $path, $directory . '/' ) === 0 && substr( $path, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a class is a test case PHPUnit would run.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @param string $path       File that declares it.
	 * @return bool Whether it is a concrete PHPUnit test case.
	 */
	private function is_concrete_test_case( string $class_name, string $path ): bool {
		if ( ! class_exists( $class_name ) ) {
			/**
			 * Files outside the autoloaded roots, such as those of a repository
			 * under test, are only declarations, so loading them is safe.
			 *
			 * @psalm-suppress UnresolvableInclude The path is only known at runtime.
			 */
			require_once $path;
		}

		if ( ! class_exists( $class_name ) ) {
			return false;
		}

		$reflection = new ReflectionClass( $class_name );

		return ! $reflection->isAbstract() && $reflection->isSubclassOf( TestCase::class );
	}

	/**
	 * Describe the suite directories for a message.
	 *
	 * @param array<string, string> $suites Suffix of each suite, keyed by
	 *                                      directory.
	 * @return string Suite directories, relative to the root.
	 */
	private function describe_suites( array $suites ): string {
		return implode( ', ', array_map( [ $this->layout, 'relative' ], array_keys( $suites ) ) );
	}

	/**
	 * Complete a problem with the explanation of the rule it breaks.
	 *
	 * @param string $identifier Identifier of the rule.
	 * @param string $problem    What is wrong, where, and what to do instead.
	 * @return string Complete message.
	 */
	private function problem( string $identifier, string $problem ): string {
		return sprintf( '[%1$s] %2$s %3$s.', $identifier, $problem, RuleManifest::get_explanation( $identifier ) );
	}
}
