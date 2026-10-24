<?php
/**
 * This fixture calls the method its context names on the template scope, to
 * prove that a protected method of SimpleView is out of reach.
 */

use MWPD\BasicScaffold\Infrastructure\TemplateScope;

/** @var TemplateScope $this */
/** @var string $method */
$method = $this->raw( 'method' );

$this->{$method}( 'reached' );
?>
<p>Reached the method.</p>
