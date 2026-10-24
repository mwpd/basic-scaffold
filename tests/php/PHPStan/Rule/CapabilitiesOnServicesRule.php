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
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Lifecycle capabilities only work on services the plugin can orchestrate.
 *
 * @implements Rule<InClassNode>
 */
final class CapabilitiesOnServicesRule implements Rule {

	private PluginNamespace $plugin_namespace;

	private string $service;

	/** @var array<string> */
	private array $capabilities;

	private string $delayed;

	private string $registerable;

	private string $has_assets;

	private PluginNamespace $infrastructure;

	/** @var array<string> */
	private array $composition_root;

	/**
	 * @param PluginNamespace $plugin_namespace Plugin code to check.
	 * @param string          $service          Service marker interface.
	 * @param array<string>   $capabilities     Lifecycle capability interfaces.
	 * @param string          $delayed          Delayed capability interface.
	 * @param string          $registerable     Registration capability interface.
	 * @param string          $has_assets       Asset capability interface.
	 * @param string          $infrastructure   Infrastructure namespace.
	 * @param array<string>   $composition_root Classes that orchestrate the services.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $service,
		array $capabilities,
		string $delayed,
		string $registerable,
		string $has_assets,
		string $infrastructure,
		array $composition_root
	) {
		$this->plugin_namespace = $plugin_namespace;
		$this->service          = $service;
		$this->capabilities     = $capabilities;
		$this->delayed          = $delayed;
		$this->registerable     = $registerable;
		$this->has_assets       = $has_assets;
		$this->infrastructure   = new PluginNamespace( $infrastructure );
		$this->composition_root = array_map( [ PluginNamespace::class, 'normalize' ], $composition_root );
	}

	public function getNodeType(): string {
		return InClassNode::class;
	}

	/**
	 * @param InClassNode $node  Class to inspect.
	 * @param Scope       $scope Scope of the class.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class = $node->getClassReflection();
		if ( ! $class->isClass()
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() )
			|| $this->infrastructure->contains_namespace( $scope->getNamespace() )
			|| in_array( PluginNamespace::normalize( $class->getName() ), $this->composition_root, true ) ) {
			return [];
		}

		$messages = [];
		if ( ! $class->implementsInterface( $this->service ) ) {
			foreach ( $this->capabilities as $capability ) {
				if ( $class->implementsInterface( $capability ) ) {
					$messages[] = sprintf(
						'Class %s implements a lifecycle capability without %s.'
						. ' Implement %s so the plugin can orchestrate it.',
						$class->getName(),
						$this->service,
						$this->service
					);
					// One missing marker is one fix, even with several capabilities.
					break;
				}
			}
		}

		// A delayed service either hooks in itself or has assets to load.
		if ( $class->implementsInterface( $this->delayed )
			&& ! $class->implementsInterface( $this->registerable )
			&& ! $class->implementsInterface( $this->has_assets ) ) {
			$messages[] = sprintf(
				'Class %s implements %s without %s. Implement %s and add its hooks in register(),'
				. ' or %s to declare its assets, so delayed registration has work to run.',
				$class->getName(),
				$this->delayed,
				$this->registerable,
				$this->registerable,
				$this->has_assets
			);
		}

		return array_map(
			static fn( string $message ): IdentifierRuleError => RuleErrorBuilder::message( $message )
				->identifier( RuleManifest::CAPABILITIES_ON_SERVICES )
				->tip( RuleManifest::get_explanation( RuleManifest::CAPABILITIES_ON_SERVICES ) )
				->build(),
			$messages
		);
	}
}
