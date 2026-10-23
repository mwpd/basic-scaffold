<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\InjectorFactory;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\SiteRunner;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UninstallBridge;

/**
 * A composition root for the lifecycle tests: the services a test hands it,
 * a delegation that gives every graph the test's recorder, and the main file
 * and uninstall callback a generated plugin would configure.
 */
final class TestLifecyclePlugin extends ServiceBasedPlugin {

	/**
	 * @var array<string,class-string|callable>
	 */
	private array $services;

	private LifecycleRecorder $recorder;

	private string $main_file = Multisite::MAIN_FILE;

	/**
	 * @var array<mixed>|null
	 */
	private ?array $uninstall_callback = [ UninstallBridge::class, 'uninstall' ];

	/**
	 * @var array<class-string>
	 */
	private array $shared_instances = [];

	private ?DebugMode $debug_mode = null;

	/**
	 * @param array<string,class-string|callable> $services         Services, in configuration order.
	 * @param LifecycleRecorder                   $recorder         Recorder every graph receives.
	 * @param SiteRunner|null                     $site_runner      Optional. Runner to use.
	 * @param Injector|null                       $injector         Optional. Injector to use.
	 * @param InjectorFactory|null                $injector_factory Optional. Injector factory to use.
	 */
	public function __construct(
		array $services,
		LifecycleRecorder $recorder,
		?SiteRunner $site_runner = null,
		?Injector $injector = null,
		?InjectorFactory $injector_factory = null
	) {
		$this->services = $services;
		$this->recorder = $recorder;
		parent::__construct( false, $injector, null, $site_runner, $injector_factory );
	}

	/**
	 * Configure no main file, as a plugin generated before #34 does.
	 */
	public function without_main_file(): self {
		$this->main_file = '';

		return $this;
	}

	/**
	 * Configure another uninstall callback, or none.
	 *
	 * @param array<mixed>|null $callback Uninstall callback.
	 */
	public function with_uninstall_callback( ?array $callback ): self {
		$this->uninstall_callback = $callback;

		return $this;
	}

	/**
	 * Share instances of the given classes within each graph.
	 *
	 * @param array<class-string> $shared_instances Classes to share.
	 */
	public function sharing( array $shared_instances ): self {
		$this->shared_instances = $shared_instances;

		return $this;
	}

	/**
	 * Bind the debug mode every graph receives, as the composition root of
	 * the scaffold binds WordPressDebugMode.
	 */
	public function with_debug_mode( DebugMode $debug_mode ): self {
		$this->debug_mode = $debug_mode;

		return $this;
	}

	protected function get_service_classes(): array {
		return $this->services;
	}

	protected function get_delegations(): array {
		$recorder   = $this->recorder;
		$debug_mode = $this->debug_mode;

		if ( null === $debug_mode ) {
			return [ LifecycleRecorder::class => static fn(): LifecycleRecorder => $recorder ];
		}

		return [
			LifecycleRecorder::class => static fn(): LifecycleRecorder => $recorder,
			DebugMode::class         => static fn(): DebugMode => $debug_mode,
		];
	}

	protected function get_shared_instances(): array {
		return $this->shared_instances;
	}

	protected function get_main_file(): string {
		return $this->main_file;
	}

	/**
	 * @psalm-suppress MixedReturnTypeCoercion The tests hand in invalid callbacks on purpose.
	 */
	protected function get_uninstall_callback(): ?array {
		/** @var array{0: class-string, 1: non-empty-string}|null The tests hand in invalid callbacks on purpose. */
		return $this->uninstall_callback;
	}
}
