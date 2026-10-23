<?php
/**
 * This fixture overrides the backend sample's template with one that fails.
 */
?>
<p>Partial output of a failing sample template.</p>
<?php
throw new RuntimeException( 'The sample template failed.' );
