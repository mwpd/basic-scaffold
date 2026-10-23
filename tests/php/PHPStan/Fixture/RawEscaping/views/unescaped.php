<?php

echo $this->raw( 'x' );
?>
<?= $this->raw( 'x' ) ?>
<?php
print $this->raw( 'x' );
$value = $this->raw( 'x' );
echo $value;
echo strtoupper( $this->raw( 'x' ) );
echo 'prefix ' . $this->raw( 'x' );
echo esc_html( $this->raw( 'x' ) ) . $this->raw( 'y' );
echo $value->title;
print $value;
?>
<?= $value ?>
<?php
echo 'first', $this->raw( 'x' ), $this->raw( 'y' );
echo esc_html( 'safe' ) . strtoupper( $value );
echo $value['title'];
$value = 'replacement';
echo $value;
if ( $condition ) {
	$conditional = $this->raw( 'x' );
}
echo $conditional;
echo esc_html( print $this->raw( 'x' ) );
echo esc_html( $inline = $this->raw( 'x' ) ), $inline;
$render = function () {
	echo $this->raw( 'x' );
	$local = $this->raw( 'x' );
	print $local;
};
