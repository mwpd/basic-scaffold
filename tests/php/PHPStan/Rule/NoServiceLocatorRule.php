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
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Feature subsystems receive collaborators instead of the service container.
 *
 * NoInjectorConfigurationRule checks the injector half of the same rule. The
 * two are separate because each PHPStan rule receives one type of node, and
 * this half needs the whole file.
 *
 * @implements Rule<FileNode>
 */
final class NoServiceLocatorRule implements Rule {

	private DependencyContext $context;
	private string $container;

	/**
	 * @param DependencyContext $context Shared dependency positions and layers.
	 * @param string $container Container interface forbidden in feature code.
	 */
	public function __construct( DependencyContext $context, string $container ) {
		$this->context   = $context;
		$this->container = PluginNamespace::normalize( $container );
	}

	public function getNodeType(): string {
		return FileNode::class;
	}

	/**
	 * @param FileNode $node File to inspect.
	 * @param Scope $scope Scope of the file.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$errors = [];
		foreach ( $this->context->get_mentions( $node, $scope ) as [ $source, $dependency, $line ] ) {
			if ( PluginNamespace::normalize( $dependency ) === $this->container
				&& $this->context->is_feature_subsystem( $source, $scope->getFile() ) ) {
				$errors[] = RuleErrorBuilder::message(
					$source . ' depends on the service container ' . $dependency . '.'
					. ' Receive the collaborator through the constructor instead of looking it up in the container.'
				)
					->identifier( RuleManifest::NO_SERVICE_LOCATOR )
					->tip( RuleManifest::get_explanation( RuleManifest::NO_SERVICE_LOCATOR ) )
					->line( $line )
					->build();
			}
		}
		return $errors;
	}
}
