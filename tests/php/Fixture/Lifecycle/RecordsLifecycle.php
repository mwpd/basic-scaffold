<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

/**
 * Records construction and each lifecycle call under the fixture's NAME.
 */
trait RecordsLifecycle {

	private LifecycleRecorder $recorder;

	public function __construct( LifecycleRecorder $recorder ) {
		$this->recorder = $recorder;
		$this->recorder->record( 'construct:' . self::NAME );
	}

	public function activate(): void {
		$this->recorder->record( 'activate:' . self::NAME );
	}

	public function deactivate(): void {
		$this->recorder->record( 'deactivate:' . self::NAME );
	}
}
