<?php
/**
 * This fixture reads context keys named like the internals of SimpleView and
 * its template scope, which must read back as context values.
 */

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
?>
<p><?= $this->path ?>|<?= $this->view_factory ?>|<?= $this->debug_mode ?></p>
<p><?= $this->configuration ?>|<?= $this->_context_ ?>|<?= $this->view ?></p>
