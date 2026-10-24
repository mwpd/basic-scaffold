<?php
/**
 * This fixture calls the method its context names on the template scope.
 */

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
/** @var string $method */
$method = $this->raw( 'method' );

$this->{$method}();
?>
<p>Called the method.</p>
