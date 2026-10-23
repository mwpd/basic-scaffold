<?php

echo esc_html( $this->raw( 'x' ) );
echo $this->plugin;
$value = $this->raw( 'x' );
echo esc_attr( $value );
echo esc_html( 'prefix ' . strtoupper( $value ) );
echo 'prefix ' . esc_html( $value );
echo esc_html( $value->title );
echo esc_url( $value ), esc_js( $value ), esc_textarea( $value ), esc_xml( $value );
echo wp_kses( $value, [] ), wp_kses_post( $value ), wp_kses_data( $value );
echo absint( $value ), intval( $value );
print \ESC_HTML( $this->RAW( 'x' ) );
?>
<?= esc_html( $value ) ?>
<?php
// Calls on another object and copies of raw variables are outside this rule.
echo $other->raw( 'x' );
$copy = $value;
echo $copy;
$render = function () {
	$local = $this->raw( 'x' );
	echo esc_html( $local );
};
// Locals in a closure do not become raw variables in the including template.
$local = 'safe';
echo $local;
