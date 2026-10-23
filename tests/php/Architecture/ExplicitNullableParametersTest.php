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

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Null defaults must not make parameter types nullable implicitly.
 */
final class ExplicitNullableParametersTest extends TestCase {

	public function test_production_parameters_are_explicitly_nullable(): void {
		$layout   = RepositoryLayout::of_plugin();
		$parser   = ( new ParserFactory() )->createForNewestSupportedVersion();
		$finder   = new NodeFinder();
		$problems = [];

		// Inspect syntax instead of relying on deprecations that only PHP 8.4+ emits.
		foreach ( $layout->get_production_files() as $path ) {
			$parameters = $finder->find(
				$parser->parse( (string) file_get_contents( $path ) ) ?? [],
				static function ( Node $node ): bool {
					return $node instanceof Param
						&& $node->type !== null
						&& ! $node->type instanceof NullableType
						&& $node->default instanceof ConstFetch
						&& strtolower( $node->default->name->toString() ) === 'null';
				}
			);

			foreach ( $parameters as $parameter ) {
				$problems[] = sprintf(
					'[%1$s] %2$s:%3$d has an implicitly nullable parameter. Add ? before its type. %4$s.',
					RuleManifest::EXPLICIT_NULLABLE_PARAMETERS,
					$layout->relative( $path ),
					$parameter->getStartLine(),
					RuleManifest::get_explanation( RuleManifest::EXPLICIT_NULLABLE_PARAMETERS )
				);
			}
		}

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}
}
