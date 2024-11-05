<?php declare( strict_types=1 );

use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */
?>
<p>Rendering works with partials: <?= $this->render_partial( 'partial' ) ?>.</p>