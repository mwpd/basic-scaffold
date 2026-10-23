<?php
/**
 * This fixture prints part of its output, then fails.
 */
?>
<p>Partial output of a failing view.</p>
<?php
throw new RuntimeException( 'The template failed.' );
