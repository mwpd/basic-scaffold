<?php
use MWPD\BasicScaffold\Infrastructure\Presenter;
use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\Tests\Fixture\View\Greeting;

/** @var TemplateScope&Greeting $this */
/** @var ?Presenter $partial_presenter */
$partial_presenter = $this->raw( 'partial_presenter' );
?>
<p>Parent: <?= $this->greeting( 'parent' ) ?></p>
<?= $this->render_partial( 'tests/php/Fixture/views/plugin/presenter-partial', null, $partial_presenter ) ?>
<p>Parent again: <?= $this->greeting( 'parent' ) ?></p>
