<?php
/**
 * This fixture leaves an output buffer open whose handler fails when the
 * buffer is cleaned, then fails itself.
 */
ob_start(
	static function (): string {
		throw new RuntimeException( 'The output handler failed.' );
	}
);
?>
<p>Never cleaned.</p>
<?php
throw new LogicException( 'The template failed.' );
