<?php

use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */
/** @var string $raw_plugin */
$raw_plugin = $this->raw( 'plugin' );

/*
 * raw() skips the view's automatic escaping, so escape it for HTML text here.
 */
?>
<div class="notice">
	<p>Hello World! from the <b><?= $this->plugin ?></b> plugin!</p>
	<p><em>Raw value: <b><?= esc_html( $raw_plugin ) ?></b></em></p>
</div>
