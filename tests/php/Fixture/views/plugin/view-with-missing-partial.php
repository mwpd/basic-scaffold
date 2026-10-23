<?php
use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */
?>
<p>Before the partial.</p>
<?= $this->render_partial( 'tests/php/Fixture/views/plugin/missing-partial' ) ?>
<p>After the partial.</p>
