<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

/**
 * Collaborator the lifecycle fixtures receive through their constructor, so a
 * test can see which of them were built, registered, activated and
 * deactivated, and in which order.
 */
final class LifecycleRecorder {

	/**
	 * @var list<string>
	 */
	private array $events = [];

	/**
	 * Objects kept by label. Keeping them stops PHP from reusing their memory
	 * for later objects, so they can be compared by identity afterwards.
	 *
	 * @var array<string,object>
	 */
	private array $objects = [];

	public function record( string $event ): void {
		$this->events[] = $event;
	}

	/**
	 * @return list<string> Recorded events, in the order they happened.
	 */
	public function events(): array {
		return $this->events;
	}

	public function keep( string $label, object $value ): void {
		$this->objects[ $label ] = $value;
	}

	public function kept( string $label ): ?object {
		return $this->objects[ $label ] ?? null;
	}
}
