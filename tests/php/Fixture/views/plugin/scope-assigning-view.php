<?php
/**
 * This fixture assigns a property when its context asks it to, then prints
 * the property.
 */

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */

if ( isset( $this->assign ) ) {
	$this->plugin = '<b>assigned</b>';
}
?>
<p><?= $this->plugin ?></p>
