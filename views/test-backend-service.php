<?php

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
/** @var string $raw_plugin */
$raw_plugin = $this->raw( 'plugin' );

/*
 * raw() skips the view's automatic escaping, so escape it for HTML text here,
 * and for an HTML attribute in the copy button.
 *
 * The copy button only works with the service's script, so it stays hidden
 * until the script shows it. Its messages are rendered here rather than in
 * the script, which then holds no text of its own.
 */
?>
<div class="notice">
	<p>Hello World! from the <b><?= $this->plugin ?></b> plugin!</p>
	<p><em>Raw value: <b><?= esc_html( $raw_plugin ) ?></b></em></p>
	<p>
		<button
			type="button"
			class="button"
			hidden
			data-mwpd-basic-scaffold-copy="<?= esc_attr( $raw_plugin ) ?>"
			data-mwpd-basic-scaffold-copied="Copied the plugin name."
			data-mwpd-basic-scaffold-failed="The plugin name could not be copied."
		>Copy the plugin name</button>
		<span class="mwpd-basic-scaffold-copy-status" role="status"></span>
	</p>
</div>
