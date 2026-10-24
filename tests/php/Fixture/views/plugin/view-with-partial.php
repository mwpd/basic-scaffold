<?php
use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
?>
<p>Rendering works with partials: <?= $this->render_partial( 'partial' ) ?>.</p>
