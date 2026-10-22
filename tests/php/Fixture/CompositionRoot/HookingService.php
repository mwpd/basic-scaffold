<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Service;

final class HookingService implements Service {

	private HookingCollaborator $collaborator;

	public function __construct( HookingCollaborator $collaborator ) {
		$this->collaborator = $collaborator;
	}

	public function get(): HookingCollaborator {
		return $this->collaborator;
	}
}
