<?php
use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */

/**
 * This fixture exists to exercise empty() on a magic context property.
 *
 * @psalm-suppress RiskyTruthyFalsyComparison
 */
$is_empty = empty( $this->some_value );
?>
isset: <?= isset( $this->some_value ) ? 'yes' : 'no' ?>, empty: <?= $is_empty ? 'yes' : 'no' ?>