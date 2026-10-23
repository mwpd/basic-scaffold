<?php
/**
 * This fixture leaves an output buffer open whose handler fails when the
 * buffer is flushed.
 */
ob_start(
	static function (): string {
		throw new RuntimeException( 'The output handler failed.' );
	}
);
?>
<p>Never flushed.</p>
