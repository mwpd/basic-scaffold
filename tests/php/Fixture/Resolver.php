<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer;

/**
 * A deferred resolver in each callable form other than a closure: a static
 * method, an instance method and an invokable object.
 *
 * Every form records the injector and the service container it receives,
 * so a test can check that resolution passes on the plugin's own.
 */
final class Resolver {

	/**
	 * Arguments each call received, in call order.
	 *
	 * @var list<array{0:Injector,1:ServiceContainer}>
	 */
	public static array $calls = [];

	/**
	 * Class name the static method resolves to.
	 */
	public const STATIC_CLASS_NAME = DummyClassWithDependency::class;

	/**
	 * @var class-string
	 */
	private string $class_name;

	/**
	 * @param class-string $class_name Class name the instance forms resolve to.
	 */
	public function __construct( string $class_name ) {
		$this->class_name = $class_name;
	}

	/**
	 * @return class-string
	 */
	public static function resolve_statically( Injector $injector, ServiceContainer $container ): string {
		self::record( $injector, $container );

		return self::STATIC_CLASS_NAME;
	}

	/**
	 * @return class-string
	 */
	public function resolve( Injector $injector, ServiceContainer $container ): string {
		self::record( $injector, $container );

		return $this->class_name;
	}

	/**
	 * @return class-string
	 */
	public function __invoke( Injector $injector, ServiceContainer $container ): string {
		self::record( $injector, $container );

		return $this->class_name;
	}

	/**
	 * Record the arguments of one call.
	 */
	public static function record( Injector $injector, ServiceContainer $container ): void {
		self::$calls[] = [ $injector, $container ];
	}
}
