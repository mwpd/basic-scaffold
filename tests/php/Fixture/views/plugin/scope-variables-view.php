<?php
/**
 * This fixture reports which variables are defined in its scope.
 */

$variables = array_keys( get_defined_vars() );
?>
<p>context: <?= in_array( 'context', $variables, true ) ? 'yes' : 'no' ?></p>
<p>buffer_level: <?= in_array( 'buffer_level', $variables, true ) ? 'yes' : 'no' ?></p>
<p>only the template path: <?= [ 'template' ] === $variables ? 'yes' : 'no' ?></p>
