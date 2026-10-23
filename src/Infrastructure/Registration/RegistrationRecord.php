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

namespace MWPD\BasicScaffold\Infrastructure\Registration;

/**
 * One decision the plugin made while registering a service.
 *
 * The plugin records a decision at the moment it makes it, with what it knew
 * then: the service's identifier, the class it resolved the declaration to
 * (never the lazy wrapper the container may hold), the state the service is
 * in, and the evidence for that state. A record is never changed afterwards;
 * a later decision about the same service is a record of its own, so the
 * records of a request read as the history of its registration.
 *
 * The evidence depends on the state:
 *
 * - waiting for an action: the registration action the service is hooked to;
 * - waiting for dependencies: the dependencies that are not registered yet,
 *   and the actions the plugin waits for until they can be;
 * - skipped: nothing when Conditional::is_needed() said no, or the dependency
 *   that cannot be registered when the plugin dropped the service;
 * - failed: the dependency that made the registration fail;
 * - registered: nothing.
 */
final class RegistrationRecord {

	/**
	 * The service was put into the container, and registered if it is
	 * Registerable.
	 */
	public const REGISTERED = 'registered';

	/**
	 * The service will not be registered in this request: it is not needed, or
	 * it depends on a service that is not.
	 */
	public const SKIPPED = 'skipped';

	/**
	 * The service waits for its Delayed registration action.
	 */
	public const WAITING_FOR_ACTION = 'waiting-for-action';

	/**
	 * The service waits for services it depends on.
	 */
	public const WAITING_FOR_DEPENDENCIES = 'waiting-for-dependencies';

	/**
	 * The dependencies of the service can never be met, and the plugin is
	 * about to throw FailedToResolveDependencies.
	 */
	public const FAILED = 'failed';

	/**
	 * Identifier of the service.
	 */
	private string $service_id;

	/**
	 * Class the declaration of the service resolved to.
	 */
	private string $class_name;

	/**
	 * State of the service, one of the constants of this class.
	 */
	private string $state;

	/**
	 * Registration actions the service waits for.
	 *
	 * @var list<string>
	 */
	private array $awaited_actions;

	/**
	 * Identifiers of the dependencies the state is about.
	 *
	 * @var list<string>
	 */
	private array $missing_dependencies;

	/**
	 * Instantiate a RegistrationRecord object.
	 *
	 * @param string   $service_id      Identifier of the service.
	 * @param string   $class_name      Class the declaration resolved to.
	 * @param string   $state           State of the service.
	 * @param string[] $awaited_actions Registration actions it waits for.
	 * @param string[] $dependencies    Dependencies the state is about.
	 *
	 * @phpstan-param list<string> $awaited_actions
	 * @phpstan-param list<string> $dependencies
	 */
	private function __construct(
		string $service_id,
		string $class_name,
		string $state,
		array $awaited_actions,
		array $dependencies
	) {
		$this->service_id           = $service_id;
		$this->class_name           = $class_name;
		$this->state                = $state;
		$this->awaited_actions      = $awaited_actions;
		$this->missing_dependencies = $dependencies;
	}

	/**
	 * Record a service that was registered.
	 *
	 * @param string $service_id Identifier of the service.
	 * @param string $class_name Class the declaration resolved to.
	 */
	public static function registered( string $service_id, string $class_name ): self {
		return new self( $service_id, $class_name, self::REGISTERED, [], [] );
	}

	/**
	 * Record a service that is not needed in this request.
	 *
	 * @param string $service_id Identifier of the service.
	 * @param string $class_name Class the declaration resolved to.
	 */
	public static function not_needed( string $service_id, string $class_name ): self {
		return new self( $service_id, $class_name, self::SKIPPED, [], [] );
	}

	/**
	 * Record a service that was dropped because a dependency of it will not be
	 * registered, and that is not needed itself.
	 *
	 * @param string $service_id    Identifier of the service.
	 * @param string $class_name    Class the declaration resolved to.
	 * @param string $dependency_id Dependency that will not be registered.
	 */
	public static function dropped( string $service_id, string $class_name, string $dependency_id ): self {
		return new self( $service_id, $class_name, self::SKIPPED, [], [ $dependency_id ] );
	}

	/**
	 * Record a service that waits for its registration action.
	 *
	 * @param string $service_id Identifier of the service.
	 * @param string $class_name Class the declaration resolved to.
	 * @param string $action     Registration action it is hooked to.
	 */
	public static function waiting_for_action( string $service_id, string $class_name, string $action ): self {
		return new self( $service_id, $class_name, self::WAITING_FOR_ACTION, [ $action ], [] );
	}

	/**
	 * Record a service that waits for services it depends on.
	 *
	 * @param string        $service_id           Identifier of the service.
	 * @param string        $class_name           Class the declaration resolved to.
	 * @param array<string> $missing_dependencies Dependencies that are not registered yet.
	 * @param array<string> $awaited_actions      Actions the plugin waits for until
	 *                                            they can be, if known.
	 */
	public static function waiting_for_dependencies(
		string $service_id,
		string $class_name,
		array $missing_dependencies,
		array $awaited_actions = []
	): self {
		return new self(
			$service_id,
			$class_name,
			self::WAITING_FOR_DEPENDENCIES,
			array_values( $awaited_actions ),
			array_values( $missing_dependencies )
		);
	}

	/**
	 * Record a service whose dependencies can never be met.
	 *
	 * @param string $service_id    Identifier of the service.
	 * @param string $class_name    Class the declaration resolved to.
	 * @param string $dependency_id Dependency that made the registration fail.
	 */
	public static function failed( string $service_id, string $class_name, string $dependency_id ): self {
		return new self( $service_id, $class_name, self::FAILED, [], [ $dependency_id ] );
	}

	/**
	 * Get the identifier of the service.
	 *
	 * @return string Service identifier.
	 */
	public function get_service_id(): string {
		return $this->service_id;
	}

	/**
	 * Get the class the declaration of the service resolved to.
	 *
	 * @return string Class name.
	 */
	public function get_class_name(): string {
		return $this->class_name;
	}

	/**
	 * Get the state of the service, one of the constants of this class.
	 *
	 * @return string State.
	 */
	public function get_state(): string {
		return $this->state;
	}

	/**
	 * Get the registration actions the service waits for.
	 *
	 * @return list<string> Actions, empty if it waits for none.
	 */
	public function get_awaited_actions(): array {
		return $this->awaited_actions;
	}

	/**
	 * Get the identifiers of the dependencies the state is about: those not
	 * registered yet, or the one that will not be registered.
	 *
	 * @return list<string> Service identifiers, empty if no dependency is involved.
	 */
	public function get_missing_dependencies(): array {
		return $this->missing_dependencies;
	}

	/**
	 * Check whether another record states the same about the same service.
	 *
	 * @param RegistrationRecord $other Record to compare with.
	 */
	public function equals( RegistrationRecord $other ): bool {
		return $this->service_id === $other->service_id
			&& $this->class_name === $other->class_name
			&& $this->state === $other->state
			&& $this->awaited_actions === $other->awaited_actions
			&& $this->missing_dependencies === $other->missing_dependencies;
	}
}
