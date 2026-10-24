<?php
use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\Tests\Fixture\View\Greeting;

/** @var TemplateScope&Greeting $this */
?>
<?php if ( $this->is_formal() ) : ?>
<p>formal</p>
<?php endif; ?>
<p><?= $this->greeting( '<b>Ada</b>' ) ?></p>
<p><?= $this->greeting( 'Grace', '?' ) ?></p>
<p><?= $this->emphasis() ?></p>
<p><?= $this->label() ?></p>
<p><?= $this->count_guests() ?> guests</p>
