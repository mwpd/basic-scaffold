<?php
use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\Tests\Fixture\View\Greeting;

/** @var TemplateScope&Greeting $this */

if ( isset( $this->fail ) ) {
	$this->failing();
}
?>
<p>Partial: <?= $this->greeting( 'partial' ) ?></p>
