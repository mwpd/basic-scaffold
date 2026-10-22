<?php
use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */
?>
<p>Rendering works with partials: <?= $this->render_partial( 'tests/php/Fixture/views/partial' ) ?>.</p>
