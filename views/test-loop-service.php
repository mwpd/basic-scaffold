<?php

use MWPD\BasicScaffold\Infrastructure\View;
use WP_Post;

/** @var View $this */
/** @var WP_Post $current_post */
$current_post = $this->raw( 'post' );
?>
<pre>
	Post title: <?= $current_post->post_title ?>
	<br>
	Post date:  <?= $current_post->post_date ?>
</pre>
<hr>