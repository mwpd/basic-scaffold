<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

use MWPD\BasicScaffold\Infrastructure as Infra;

/** @method Infra\ServiceContainer container() */
final class Aliased {}
