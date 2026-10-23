<?php
/*
 * The registration panel in Query Monitor, printed by the optional
 * RegistrationPanel infrastructure service, not a sample to replace.
 *
 * Every value comes from the plugin's configuration and the services' own
 * declarations, which filters can change, so each one is escaped for the
 * HTML it lands in. The panel ID and title are read as properties, which the
 * view escapes already; the records are objects, read raw, and each field is
 * escaped where it is printed.
 */

use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord;
use MWPD\BasicScaffold\Infrastructure\View;

/** @var View $this */

/** @var list<RegistrationRecord> $records */
$records = $this->raw( 'records' );

/** @var array<string,array{record: RegistrationRecord, transitions: int}> $services */
$services = $this->raw( 'services' );

$state_labels = [
	RegistrationRecord::REGISTERED               => 'Registered',
	RegistrationRecord::SKIPPED                  => 'Skipped',
	RegistrationRecord::WAITING_FOR_ACTION       => 'Waiting for an action',
	RegistrationRecord::WAITING_FOR_DEPENDENCIES => 'Waiting for dependencies',
	RegistrationRecord::FAILED                   => 'Failed',
];

/*
 * Describe the evidence of a record, as plain text to escape when printed.
 */
$describe_evidence = static function ( RegistrationRecord $record ): string {
	$evidence = [];

	if ( [] !== $record->get_missing_dependencies() ) {
		$label      = RegistrationRecord::WAITING_FOR_DEPENDENCIES === $record->get_state()
			? 'Missing dependencies: '
			: 'Dependency not registered: ';
		$evidence[] = $label . implode( ', ', $record->get_missing_dependencies() );
	}

	if ( [] !== $record->get_awaited_actions() ) {
		$evidence[] = 'Awaited actions: ' . implode( ', ', $record->get_awaited_actions() );
	}

	if ( [] === $evidence && RegistrationRecord::SKIPPED === $record->get_state() ) {
		$evidence[] = 'Not needed in this request';
	}

	return implode( '; ', $evidence );
};
?>
<div class="qm" id="<?= $this->panel_id ?>" role="tabpanel"
	aria-labelledby="<?= $this->panel_id ?>-caption" tabindex="-1">
	<table>
		<caption>
			<h2 id="<?= $this->panel_id ?>-caption"><?= $this->title ?>: current state</h2>
		</caption>
		<thead>
			<tr>
				<th scope="col">Service</th>
				<th scope="col">Class</th>
				<th scope="col">State</th>
				<th scope="col">Evidence</th>
				<th scope="col" class="qm-num">Records</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $services as $service ) : ?>
				<?php $record = $service['record']; ?>
				<tr class="<?= RegistrationRecord::REGISTERED === $record->get_state() ? '' : 'qm-warn' ?>">
					<td class="qm-ltr"><code><?= esc_html( $record->get_service_id() ) ?></code></td>
					<td class="qm-ltr"><code><?= esc_html( $record->get_class_name() ) ?></code></td>
					<td><?= esc_html( $state_labels[ $record->get_state() ] ?? $record->get_state() ) ?></td>
					<td class="qm-ltr"><?= esc_html( $describe_evidence( $record ) ) ?></td>
					<td class="qm-num"><?= esc_html( (string) $service['transitions'] ) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<table>
		<caption>
			<h2><?= $this->title ?>: history</h2>
		</caption>
		<thead>
			<tr>
				<th scope="col" class="qm-num">#</th>
				<th scope="col">Service</th>
				<th scope="col">Class</th>
				<th scope="col">State</th>
				<th scope="col">Evidence</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $records as $index => $record ) : ?>
				<tr>
					<td class="qm-num"><?= esc_html( (string) ( $index + 1 ) ) ?></td>
					<td class="qm-ltr"><code><?= esc_html( $record->get_service_id() ) ?></code></td>
					<td class="qm-ltr"><code><?= esc_html( $record->get_class_name() ) ?></code></td>
					<td><?= esc_html( $state_labels[ $record->get_state() ] ?? $record->get_state() ) ?></td>
					<td class="qm-ltr"><?= esc_html( $describe_evidence( $record ) ) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
