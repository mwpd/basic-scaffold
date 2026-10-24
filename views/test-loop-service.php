<?php

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
/** @var WP_Post $current_post */
$current_post = $this->raw( 'post' );

/*
 * A raw value is printed as it is, so the template escapes every field it
 * prints for the context it lands in.
 */
?>
<pre>
	Post title: <?= esc_html( $current_post->post_title ) ?>
	<br>
	Post date:  <?= esc_html( $current_post->post_date ) ?>
</pre>
<hr>
