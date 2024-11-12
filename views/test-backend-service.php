<?php

use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */
?>
<div class="notice">
	<p>Hello World! from the <b><?= $this->plugin ?></b> plugin!</p>
	<p><em>Raw value: <b><?= $this->raw( 'plugin' ) ?></b></em></p>
</div>