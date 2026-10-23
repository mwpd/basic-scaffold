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

use MWPD\BasicScaffold\Tests\Architecture\RepositoryLayout;
use MWPD\BasicScaffold\Tests\Architecture\RuleManifest;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * The plugin factory is an entry into the graph, never a service locator.
 *
 * @implements Rule<StaticCall>
 */
final class PluginFactoryOnlyAtEdgesRule implements Rule {

	private PluginNamespace $plugin_namespace;

	/**
	 * @var array<string>
	 */
	private array $views;

	/**
	 * @var array<string>
	 */
	private array $factories;

	/**
	 * @var array<string>
	 */
	private array $bridges;

	private string $bootstrap;

	/**
	 * @param PluginNamespace $plugin_namespace Code the rule applies to.
	 * @param array<string>   $views            Production view directories.
	 * @param array<string>   $factories        Plugin factories reached only at the edges.
	 * @param array<string>   $bridges          Allowed entry points (`Class::method`).
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $views, array $factories, array $bridges ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->views            = array_map( static fn( string $view ): string => rtrim( $view, '/' ), $views );
		$this->factories        = array_map( [ PluginNamespace::class, 'normalize' ], $factories );
		$this->bridges          = array_map( [ PluginNamespace::class, 'normalize' ], $bridges );
		// The bootstrap is analysed too; reuse the repository's declared entry point.
		$this->bootstrap = RepositoryLayout::of_plugin()->get_bootstrap();
	}

	public function getNodeType(): string {
		return StaticCall::class;
	}

	/**
	 * @param StaticCall $node  Static call to inspect.
	 * @param Scope      $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node->class instanceof Name || ! $this->applies_to( $scope ) || $this->is_bridge( $scope ) ) {
			return [];
		}

		$factory = $scope->resolveName( $node->class );
		if ( ! in_array( PluginNamespace::normalize( $factory ), $this->factories, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'Static call to %s in %s uses the plugin factory as a service locator.',
					$factory,
					CalledFunction::describe_location( $scope )
				)
				. ' Receive what you need through the constructor, or receive the injector for on-demand creation.'
				. ' A static WordPress entry point belongs in a method listed in'
				. ' parameters.architecture.pluginFactoryOnlyAtEdges.bridges; get the graph and delegate immediately'
				. ' to instance services.'
			)
				->identifier( RuleManifest::PLUGIN_FACTORY_ONLY_AT_EDGES )
				->tip( RuleManifest::get_explanation( RuleManifest::PLUGIN_FACTORY_ONLY_AT_EDGES ) )
				->build(),
		];
	}

	private function applies_to( Scope $scope ): bool {
		if ( $scope->getFile() === $this->bootstrap ) {
			return false;
		}

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

	private function is_bridge( Scope $scope ): bool {
		$class    = $scope->getClassReflection();
		$function = $scope->getFunction();

		// A deferred closure is no longer the WordPress entry point itself.
		return $class !== null && $function instanceof MethodReflection && ! $scope->isInAnonymousFunction()
			&& in_array(
				PluginNamespace::normalize( $class->getName() . '::' . $function->getName() ),
				$this->bridges,
				true
			);
	}
}
