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

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\Type;

/**
 * Shared dependency positions and layer boundaries for architecture rules.
 *
 * Imports, native types, code and documentation count for the same source in
 * every rule that inspects dependencies. Keep this definition in one place.
 */
final class DependencyContext {

	public const EXCEPTIONS     = 'the exceptions';
	public const INFRASTRUCTURE = 'the infrastructure';
	public const ROOT           = 'the composition root';
	public const TESTS          = 'the tests';
	public const TOP_LEVEL      = 'a top-level class';
	public const VIEW           = 'a view template';

	/**
	 * Root namespace of the plugin, normalized, with a trailing separator.
	 */
	private string $root;

	/**
	 * Namespaces of the tests, normalized, with a trailing separator.
	 *
	 * @var array<string>
	 */
	private array $tests;

	/**
	 * Namespace of the exceptions, normalized, with a trailing separator.
	 */
	private string $exceptions;

	/**
	 * Namespace of the infrastructure, normalized, with a trailing separator.
	 */
	private string $infrastructure;

	/**
	 * Classes of the composition root, normalized.
	 *
	 * @var array<string>
	 */
	private array $composition_root;

	/**
	 * Directories of the production view templates, without trailing slashes.
	 *
	 * @var array<string>
	 */
	private array $views;

	/**
	 * Reflection, to tell interfaces from classes.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Resolver of the types in documentation.
	 */
	private FileTypeMapper $file_type_mapper;

	/**
	 * Instantiate a DependencyContext object.
	 *
	 * @param string             $root_namespace   Root namespace of the plugin.
	 * @param array<string>      $test_namespaces  Namespaces of the tests.
	 * @param string             $exceptions       Namespace of the exceptions.
	 * @param string             $infrastructure   Namespace of the
	 *                                             infrastructure.
	 * @param array<string>      $composition_root Classes of the composition
	 *                                             root.
	 * @param array<string>      $views            Directories of the production
	 *                                             view templates.
	 * @param ReflectionProvider $reflection       Reflection, to tell
	 *                                             interfaces from classes.
	 * @param FileTypeMapper     $file_type_mapper Resolver of the types in
	 *                                             documentation.
	 */
	public function __construct(
		string $root_namespace,
		array $test_namespaces,
		string $exceptions,
		string $infrastructure,
		array $composition_root,
		array $views,
		ReflectionProvider $reflection,
		FileTypeMapper $file_type_mapper
	) {
		$this->root             = PluginNamespace::normalize( $root_namespace ) . '\\';
		$this->tests            = array_map(
			static fn( string $name ): string => PluginNamespace::normalize( $name ) . '\\',
			$test_namespaces
		);
		$this->exceptions       = PluginNamespace::normalize( $exceptions ) . '\\';
		$this->infrastructure   = PluginNamespace::normalize( $infrastructure ) . '\\';
		$this->composition_root = array_map( [ PluginNamespace::class, 'normalize' ], $composition_root );
		$this->views            = array_map( static fn( string $view ): string => rtrim( $view, '/' ), $views );
		$this->reflection       = $reflection;
		$this->file_type_mapper = $file_type_mapper;
	}

	/**
	 * Get every dependency mention, deduplicated by source and dependency.
	 *
	 * @param FileNode $node  File to inspect.
	 * @param Scope    $scope Scope of the file.
	 * @return array<string, array{string, string, int}> Source, dependency and line.
	 */
	public function get_mentions( FileNode $node, Scope $scope ): array {
		$mentions = [];
		$nodes    = $node->getNodes();
		$this->collect( $nodes, $scope, '', $this->get_file_source( $nodes, '', $scope->getFile() ), null, $mentions );
		return $mentions;
	}

	/**
	 * Whether code belongs to a feature subsystem rather than a wiring layer.
	 *
	 * @param string $name Name of the code.
	 * @param string $file Path of its file.
	 * @return bool Whether feature rules apply.
	 */
	public function is_feature_subsystem( string $name, string $file ): bool {
		$layer = $this->get_source_layer( $name, $file );
		return $layer !== null && substr( $layer, -10 ) === ' subsystem';
	}

	/**
	 * Collect what the given nodes mention, each with the code it counts for.
	 *
	 * @param array<Node>                                        $nodes             Nodes to search.
	 * @param Scope                                              $scope             Scope of the file.
	 * @param string                                             $current_namespace Current namespace.
	 * @param string                                             $source            Code the mentions
	 *                                                                              count for.
	 * @param string|null                                        $doc_class         Class whose context
	 *                                                                              resolves documentation.
	 * @param array<string, array{0: string, 1: string, 2: int}> $mentions          Mentions found, each
	 *                                                                              as source, dependency
	 *                                                                              and line.
	 */
	private function collect(
		array $nodes,
		Scope $scope,
		string $current_namespace,
		string $source,
		?string $doc_class,
		array &$mentions
	): void {
		foreach ( $nodes as $node ) {
			$this->collect_node( $node, $scope, $current_namespace, $source, $doc_class, $mentions );
		}
	}

	/**
	 * Collect what a node and its children mention.
	 *
	 * @param Node                                               $node              Node to search.
	 * @param Scope                                              $scope             Scope of the file.
	 * @param string                                             $current_namespace Current namespace.
	 * @param string                                             $source            Code the mentions
	 *                                                                              count for.
	 * @param string|null                                        $doc_class         Class whose context
	 *                                                                              resolves documentation.
	 * @param array<string, array{0: string, 1: string, 2: int}> $mentions          Mentions found.
	 */
	private function collect_node(
		Node $node,
		Scope $scope,
		string $current_namespace,
		string $source,
		?string $doc_class,
		array &$mentions
	): void {
		if ( $node instanceof Namespace_ ) {
			$current_namespace = $node->name === null ? '' : $node->name->toString();
			$file_source       = $this->get_file_source( $node->stmts, $current_namespace, $scope->getFile() );
			$this->collect( $node->stmts, $scope, $current_namespace, $file_source, null, $mentions );
			return;
		}

		if ( $node instanceof Class_ && $node->name === null ) {
			// PHPStan knows an anonymous class by a generated name, which its
			// documentation is resolved with.
			$source    = ltrim( $current_namespace . '\\class@anonymous', '\\' );
			$doc_class = $this->reflection->getAnonymousClassReflection( $node, $scope )->getName();
		} elseif ( $node instanceof ClassLike && $node->name !== null ) {
			$source    = ltrim( $current_namespace . '\\' . $node->name->toString(), '\\' );
			$doc_class = $this->reflection->hasClass( $source ) ? $source : null;
		}

		if ( $node instanceof Use_ || $node instanceof GroupUse ) {
			$this->collect_imports( $node, $source, $mentions );
			return;
		}

		$doc = $node->getDocComment();
		if ( $doc !== null ) {
			$this->collect_documented( $doc, $node, $scope->getFile(), $source, $doc_class, $mentions );
		}

		if ( $node instanceof Name ) {
			if ( ! $node->isSpecialClassName() ) {
				$this->add( $mentions, $source, $node->toString(), $node->getStartLine() );
			}
			return;
		}

		foreach ( $node->getSubNodeNames() as $sub_node_name ) {
			// Names of functions and constants are not classes, but a function
			// called through an expression, such as a closure, is code.
			if ( ( $node instanceof FuncCall || $node instanceof ConstFetch )
				&& $sub_node_name === 'name'
				&& $node->name instanceof Name ) {
				continue;
			}

			$children = $node->$sub_node_name;
			foreach ( is_array( $children ) ? $children : [ $children ] as $child ) {
				if ( $child instanceof Node ) {
					$this->collect_node( $child, $scope, $current_namespace, $source, $doc_class, $mentions );
				}
			}
		}
	}

	/**
	 * Collect the classes a `use` statement imports.
	 *
	 * @param Use_|GroupUse                                      $node     Import statement.
	 * @param string                                             $source   Code the imports count for.
	 * @param array<string, array{0: string, 1: string, 2: int}> $mentions Mentions found.
	 */
	private function collect_imports( Node $node, string $source, array &$mentions ): void {
		$prefix = $node instanceof GroupUse ? $node->prefix->toString() . '\\' : '';

		foreach ( $node->uses as $use ) {
			$type = $use->type !== Use_::TYPE_UNKNOWN ? $use->type : $node->type;

			$imported = $prefix . $use->name->toString();

			// Imported functions and constants are not classes, and neither is
			// an alias of a namespace; the names used through it count instead.
			if ( $type === Use_::TYPE_NORMAL && $this->reflection->hasClass( $imported ) ) {
				$this->add( $mentions, $source, $imported, $use->getStartLine() );
			}
		}
	}

	/**
	 * Collect the classes a doc comment mentions, in any of its tags.
	 *
	 * @param Doc                                                $doc       Doc comment.
	 * @param Node                                               $node      Node it documents.
	 * @param string                                             $file      Path of the file.
	 * @param string                                             $source    Code it counts for.
	 * @param string|null                                        $doc_class Class whose context
	 *                                                                      resolves it.
	 * @param array<string, array{0: string, 1: string, 2: int}> $mentions  Mentions found.
	 */
	private function collect_documented(
		Doc $doc,
		Node $node,
		string $file,
		string $source,
		?string $doc_class,
		array &$mentions
	): void {
		// A trait's own code resolves its names like a class does.
		$function = $node instanceof ClassMethod || $node instanceof Function_ ? $node->name->toString() : null;
		$phpdoc   = $this->file_type_mapper->getResolvedPhpDoc( $file, $doc_class, null, $function, $doc->getText() );

		foreach ( self::get_documented_types( $phpdoc ) as $type ) {
			foreach ( $type->getReferencedClasses() as $referenced ) {
				$this->add( $mentions, $source, $referenced, $doc->getStartLine() );
			}
		}

		// A type alias imported from another class depends on that class.
		foreach ( $phpdoc->getTypeAliasImportTags() as $tag ) {
			$this->add( $mentions, $source, $tag->getImportedFrom(), $doc->getStartLine() );
		}

		// Resolving a type can erase the class it came from, as for a class
		// constant, so fully qualified names in the text count as well.
		preg_match_all( '/\\\\[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', $doc->getText(), $matches );
		foreach ( $matches[0] as $qualified ) {
			$this->add( $mentions, $source, $qualified, $doc->getStartLine() );
		}
	}

	/**
	 * Get every type a resolved doc comment mentions.
	 *
	 * @param ResolvedPhpDocBlock $phpdoc Resolved doc comment.
	 * @return array<Type> Types.
	 */
	private static function get_documented_types( ResolvedPhpDocBlock $phpdoc ): array {
		$types = [];

		foreach ( [ $phpdoc->getVarTags(), $phpdoc->getParamTags(), $phpdoc->getMixinTags() ] as $tags ) {
			foreach ( $tags as $tag ) {
				$types[] = $tag->getType();
			}
		}

		foreach ( [ $phpdoc->getExtendsTags(), $phpdoc->getImplementsTags(), $phpdoc->getUsesTags() ] as $tags ) {
			foreach ( $tags as $tag ) {
				$types[] = $tag->getType();
			}
		}

		foreach ( [ $phpdoc->getAssertTags(), $phpdoc->getParamOutTags() ] as $tags ) {
			foreach ( $tags as $tag ) {
				$types[] = $tag->getType();
			}
		}

		foreach ( [ $phpdoc->getRequireExtendsTags(), $phpdoc->getRequireImplementsTags() ] as $tags ) {
			foreach ( $tags as $tag ) {
				$types[] = $tag->getType();
			}
		}

		foreach ( [ $phpdoc->getReturnTag(), $phpdoc->getThrowsTag() ] as $tag ) {
			if ( $tag !== null ) {
				$types[] = $tag->getType();
			}
		}

		foreach ( $phpdoc->getPropertyTags() as $tag ) {
			$types[] = $tag->getReadableType();
			$types[] = $tag->getWritableType();
		}

		foreach ( $phpdoc->getMethodTags() as $tag ) {
			$types[] = $tag->getReturnType();
			foreach ( $tag->getParameters() as $parameter ) {
				$types[] = $parameter->getType();
			}
		}

		foreach ( $phpdoc->getTemplateTags() as $tag ) {
			$types[] = $tag->getBound();
			$types[] = $tag->getDefault();
		}

		foreach ( $phpdoc->getParamClosureThisTags() as $tag ) {
			$types[] = $tag->getType();
		}

		return array_values( array_filter( $types ) );
	}

	/**
	 * Record a mention, keeping only the first of each.
	 *
	 * @param array<string, array{0: string, 1: string, 2: int}> $mentions   Mentions found.
	 * @param string                                             $source     Code it counts for.
	 * @param string                                             $dependency Class mentioned.
	 * @param int                                                $line       Line of the mention.
	 */
	private function add( array &$mentions, string $source, string $dependency, int $line ): void {
		$key = PluginNamespace::normalize( $source ) . '|' . PluginNamespace::normalize( $dependency );

		if ( ! isset( $mentions[ $key ] ) || $mentions[ $key ][2] > $line ) {
			$mentions[ $key ] = [ $source, ltrim( $dependency, '\\' ), $line ];
		}
	}

	/**
	 * Get the code that a file's imports and loose code count for.
	 *
	 * That is the class, interface or trait the file declares. A file without
	 * one, such as a view template, counts for its namespace.
	 *
	 * @param array<Node> $nodes             Top-level nodes of the file or
	 *                                       namespace.
	 * @param string      $current_namespace Namespace of the nodes.
	 * @param string      $file              Path of the file.
	 * @return string Name of the code.
	 */
	private function get_file_source( array $nodes, string $current_namespace, string $file ): string {
		foreach ( $nodes as $node ) {
			if ( $node instanceof ClassLike && $node->name !== null ) {
				return ltrim( $current_namespace . '\\' . $node->name->toString(), '\\' );
			}
		}

		return $current_namespace === '' ? $file : $current_namespace . '\\' . basename( $file, '.php' );
	}

	/**
	 * Get the layer of the code a mention counts for.
	 *
	 * @param string $name Name of the code, or the path of a file without one.
	 * @param string $file Path of the file.
	 * @return string|null Layer, or null for code the rule does not check.
	 */
	public function get_source_layer( string $name, string $file ): ?string {
		foreach ( $this->views as $views ) {
			if ( strpos( $file, $views . '/' ) === 0 ) {
				return self::VIEW;
			}
		}

		$layer = $this->get_layer( $name );

		// The tests may depend on anything.
		return $layer === self::TESTS ? null : $layer;
	}

	/**
	 * Get the layer of a class, interface or trait.
	 *
	 * @param string $name Fully qualified name.
	 * @return string|null Layer, or null for code outside the plugin.
	 */
	public function get_layer( string $name ): ?string {
		$normalized = PluginNamespace::normalize( $name );

		if ( strpos( $normalized, $this->root ) !== 0 ) {
			return null;
		}

		foreach ( $this->tests as $tests ) {
			if ( strpos( $normalized, $tests ) === 0 ) {
				return self::TESTS;
			}
		}

		if ( in_array( $normalized, $this->composition_root, true ) ) {
			return self::ROOT;
		}

		if ( strpos( $normalized, $this->exceptions ) === 0 ) {
			return self::EXCEPTIONS;
		}

		if ( strpos( $normalized, $this->infrastructure ) === 0 ) {
			return self::INFRASTRUCTURE;
		}

		$relative = substr( $normalized, strlen( $this->root ) );
		$position = strpos( $relative, '\\' );

		// A feature subsystem is named after its top-level namespace.
		return $position === false
			? self::TOP_LEVEL
			: 'the ' . substr( $name, strlen( $this->root ), $position ) . ' subsystem';
	}
}
