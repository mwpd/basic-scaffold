<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Nette\Neon\Neon;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use SebastianBergmann\Diff\Differ;

/**
 * A portable listing of the infrastructure's callable and extensible surface.
 */
final class InfrastructureApiSnapshot {

	public const PATH = 'tests/php/Architecture/infrastructure-api.txt';

	private RepositoryLayout $layout;

	private string $namespace;

	private string $infrastructure;

	public function __construct( RepositoryLayout $layout ) {
		$this->layout = $layout;
		/**
		 * Neon declares a native mixed return type, which Psalm reads as a
		 * reserved word when targeting PHP 7.4, as in RepositoryLayout.
		 *
		 * @psalm-suppress ReservedWord
		 */
		$config = Neon::decodeFile( $layout->get_phpstan_config() );
		assert( is_array( $config ) && is_array( $config['parameters'] ?? null ) );
		$settings = $config['parameters']['architecture'] ?? null;
		assert( is_array( $settings ) && is_string( $settings['namespace'] ?? null ) );
		$direction = $settings['dependencyDirection'] ?? null;
		assert( is_array( $direction ) && is_string( $direction['infrastructure'] ?? null ) );
		$this->namespace      = trim( $settings['namespace'], '\\' ) . '\\';
		$this->infrastructure = trim( $direction['infrastructure'], '\\' ) . '\\';
	}

	public function build(): string {
		$lines = [];
		foreach ( $this->layout->get_production_files() as $path ) {
			foreach ( PhpFile::from_path( $path )->get_declarations() as $declaration ) {
				$name = $declaration['name'];
				if ( strpos( $name, $this->infrastructure ) !== 0 ) {
					continue;
				}
				assert( class_exists( $name ) || interface_exists( $name ) || trait_exists( $name ) );
				$class = new ReflectionClass( $name );
				$lines = array_merge( $lines, $this->members( $class ) );
			}
		}
		sort( $lines, SORT_STRING );

		return $lines === [] ? '' : implode( "\n", $lines ) . "\n";
	}

	/** @return list<string> Problems, including a unified diff and the fix. */
	public function check(): array {
		$path     = $this->layout->absolute( self::PATH );
		$expected = is_file( $path ) ? (string) file_get_contents( $path ) : '';
		$actual   = $this->build();
		if ( is_file( $path ) && $expected === $actual ) {
			return [];
		}

		return [
			'[' . RuleManifest::INFRASTRUCTURE_API_SNAPSHOT . '] ' . self::PATH
			. ' differs from the infrastructure API. Review the change, then run composer api:snapshot.'
			. ' A PR that removes or changes existing API needs the breaking label and a maintainer ruling. '
			. RuleManifest::get_explanation( RuleManifest::INFRASTRUCTURE_API_SNAPSHOT ) . "\n"
			. ( new Differ() )->diff( $expected, $actual ),
		];
	}

	/**
	 * Inherited members are callable through the child too. Never enumerate
	 * implemented interfaces: PHP 8 implicitly adds Stringable to some classes.
	 *
	 * @param ReflectionClass<object> $reflection Type being listed.
	 * @return list<string> Declaration and member lines.
	 */
	private function members( ReflectionClass $reflection ): array {
		$name = $this->relative( $reflection->getName() );
		$kind = $reflection->isInterface() ? 'interface' : ( $reflection->isTrait() ? 'trait' : 'class' );
		if ( $kind === 'class' ) {
			$kind = ( $reflection->isFinal() ? 'final ' : ( $reflection->isAbstract() ? 'abstract ' : '' ) ) . $kind;
		}
		$lines = [ $name . ' ' . $kind ];
		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED ) as $method ) {
			// PHP owns internal signatures (ArrayObject, Countable, ArrayAccess),
			// including their changes between runtimes. Only snapshot our API.
			if ( ! $method->isInternal() && ( ! $reflection->isFinal() || $method->isPublic() ) ) {
				$lines[] = $name . '::' . $this->method( $method );
			}
		}
		if ( $reflection->isFinal() ) {
			return $lines;
		}
		foreach ( $reflection->getReflectionConstants() as $constant ) {
			if ( ! $constant->isPrivate() ) {
				$lines[] = $name . '::' . ( $constant->isPublic() ? 'public' : 'protected' )
					. ' const ' . $constant->getName() . ' = ' . $this->value( $constant->getValue() );
			}
		}
		$defaults   = $reflection->getDefaultProperties();
		$properties = $reflection->getProperties( ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED );
		foreach ( $properties as $property ) {
			$type    = $this->type( $property->getType() );
			$lines[] = $name . '::' . ( $property->isPublic() ? 'public' : 'protected' )
				. ( $property->isStatic() ? ' static' : '' ) . ( $type === '' ? '' : ' ' . $type )
				. ' $' . $property->getName()
				. ( array_key_exists( $property->getName(), $defaults )
					? ' = ' . $this->value( $defaults[ $property->getName() ] ) : '' );
		}

		return $lines;
	}

	private function method( ReflectionMethod $method ): string {
		$parameters = array_map( [ $this, 'parameter' ], $method->getParameters() );
		$return     = $this->type( $method->getReturnType() );

		return ( $method->isPublic() ? 'public' : 'protected' )
			. ( $method->isAbstract() ? ' abstract' : '' ) . ( $method->isFinal() ? ' final' : '' )
			. ( $method->isStatic() ? ' static' : '' ) . ' function ' . ( $method->returnsReference() ? '&' : '' )
			. $method->getName() . '(' . ( $parameters === [] ? '' : ' ' . implode( ', ', $parameters ) . ' ' ) . ')'
			. ( $return === '' ? '' : ': ' . $return );
	}

	private function parameter( ReflectionParameter $parameter ): string {
		$type = $this->type( $parameter->getType() );

		return ( $type === '' ? '' : $type . ' ' ) . ( $parameter->isPassedByReference() ? '&' : '' )
			. ( $parameter->isVariadic() ? '...' : '' ) . '$' . $parameter->getName()
			. ( $parameter->isDefaultValueAvailable() ? ' = ' . $this->value( $parameter->getDefaultValue() ) : '' );
	}

	private function type( ?ReflectionType $type ): string {
		if ( $type === null ) {
			return '';
		}
		// PHP 7.4 declarations only have named types. Fail rather than silently
		// omitting a type if production code ever exceeds that language floor.
		assert( $type instanceof ReflectionNamedType );
		$name = $type->getName();
		if ( ! $type->isBuiltin() && ! in_array( $name, [ 'self', 'parent', 'static' ], true ) ) {
			$name = $this->relative( $name );
		}

		return ( $type->allowsNull() && ! in_array( $name, [ 'mixed', 'null' ], true ) ? '?' : '' ) . $name;
	}

	/**
	 * Resolve constant defaults to values: reflection's spelling of constant
	 * expressions varies between PHP versions. JSON keeps strings on one line.
	 *
	 * @param mixed $value Constant, property or parameter default.
	 */
	private function value( $value ): string {
		if ( is_array( $value ) ) {
			$items   = [];
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			foreach ( $value as $key => $item ) {
				$items[] = ( $is_list ? '' : $this->value( $key ) . ' => ' ) . $this->value( $item );
			}

			return '[' . implode( ', ', $items ) . ']';
		}
		if ( is_string( $value ) ) {
			$value = str_replace(
				[ $this->namespace, $this->layout->absolute( '' ) ],
				[ '', '<root>/' ],
				$value
			);
		}

		return json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
		);
	}

	private function relative( string $name ): string {
		return strpos( $name, $this->namespace ) === 0 ? substr( $name, strlen( $this->namespace ) ) : '\\' . $name;
	}
}
