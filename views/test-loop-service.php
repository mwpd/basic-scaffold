<?php

use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\SampleSubsystem\PostHeader;

/** @var TemplateScope&PostHeader $this */

/*
 * The service passed a PostHeader presenter, so the template can call its
 * methods on $this. Their string results come back escaped for HTML, like
 * context properties do.
 */
?>
<pre>
	Post title: <?= $this->title() ?>
	<br>
	Post date:  <?= $this->date() ?>
</pre>
<hr>
