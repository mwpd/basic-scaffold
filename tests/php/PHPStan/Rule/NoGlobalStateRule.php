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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use MWPD\BasicScaffold\Tests\Architecture\RuleManifest;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Global_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Dependencies are injected; request input is read only at a listed boundary.
 *
 * @implements Rule<Node>
 */
final class NoGlobalStateRule implements Rule {

	private const INPUT = [ '_GET', '_POST', '_REQUEST', '_COOKIE', '_SERVER', '_FILES', '_ENV' ];

	private PluginNamespace $plugin_namespace;

	/**
	 * Production view directories, without trailing slashes.
	 *
	 * @var array<string>
	 */
	private array $views;

	/**
	 * Classes allowed to read request input, normalized as PHP class names.
	 *
	 * @var array<string>
	 */
	private array $input_adapters;

	/**
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param array<string>   $views            Production view directories.
	 * @param array<string>   $input_adapters   Classes that normalize request input.
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $views, array $input_adapters ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->views            = array_map( static fn( string $view ): string => rtrim( $view, '/' ), $views );
		$this->input_adapters   = array_map( [ PluginNamespace::class, 'normalize' ], $input_adapters );
	}

	public function getNodeType(): string {
		return Node::class;
	}

	/**
	 * @param Node  $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node instanceof Global_ && ! $node instanceof Variable ) {
			return [];
		}

		if ( ! $this->applies_to( $scope ) ) {
			return [];
		}

		$class = $scope->getClassReflection();
		$owner = $class === null
			? basename( $scope->getFile() )
			: ( $class->isAnonymous() ? 'class@anonymous' : $class->getName() );

		if ( $node instanceof Global_ ) {
			return [ $this->build_error( sprintf( 'Global statement in %s pulls state from globals.', $owner ) ) ];
		}

		if ( $node->name === 'GLOBALS' ) {
			return [ $this->build_error( sprintf( '$GLOBALS in %s accesses global state.', $owner ) ) ];
		}

		if ( is_string( $node->name ) && in_array( $node->name, self::INPUT, true )
			&& ( $class === null
				|| ! in_array( PluginNamespace::normalize( $class->getName() ), $this->input_adapters, true ) ) ) {
			return [
				$this->build_error(
					sprintf( 'Request input $%s in %s bypasses an input adapter.', $node->name, $owner )
				),
			];
		}

		return [];
	}

	private function applies_to( Scope $scope ): bool {
		if ( $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return true;
		}

		foreach ( $this->views as $views ) {
			if ( strpos( $scope->getFile(), $views . '/' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	private function build_error( string $problem ): IdentifierRuleError {
		return RuleErrorBuilder::message(
			$problem
			. ' Receive the value through the constructor; for collaborators such as $wpdb or WP_Query,'
			. ' configure a delegation in the composition root. For state that changes while the request runs,'
			. ' such as the post being rendered, inject an interface and ask it at call time, as SampleLoopService'
			. ' asks MainLoop in src/SampleSubsystem/.'
			. ' Read request input only in a class listed in parameters.architecture.noGlobalState.inputAdapters'
			. ' that sanitizes and normalizes it into an internal type.'
		)
			->identifier( RuleManifest::NO_GLOBAL_STATE )
			->tip( RuleManifest::get_explanation( RuleManifest::NO_GLOBAL_STATE ) )
			->build();
	}
}
