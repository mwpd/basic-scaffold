<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Infrastructure\QueryMonitor;

use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use QM_Collector;
use QM_Output_Html;

/**
 * Create the output that prints the registration panel in Query Monitor.
 *
 * Query Monitor 4 renders its own panels in the browser, from data it
 * collects. Panels of other plugins are rendered on the server instead: Query
 * Monitor prints the HTML their output returns into a fallback container,
 * and shows it when the panel is opened. So no build step and no script is
 * needed; the panel is a view like any other, escaped by default.
 *
 * The output's class extends QM_Output_Html, so it is declared in create(),
 * as an anonymous class, and not in a file of its own. Loading this factory
 * loads nothing of Query Monitor.
 */
final class RegistrationOutputFactory implements OutputFactory {

	/**
	 * Template of the panel.
	 */
	public const TEMPLATE = 'views/query-monitor-registration';

	/**
	 * Factory for the view of the panel.
	 */
	private ViewFactory $view_factory;

	/**
	 * Title of the panel in Query Monitor's menu.
	 */
	private string $panel_title;

	/**
	 * Instantiate a RegistrationOutputFactory object.
	 *
	 * @param ViewFactory $view_factory Factory for the view of the panel.
	 * @param string      $panel_title  Title of the panel in Query Monitor's
	 *                                  menu, which names the plugin, so that
	 *                                  the panels of two plugins built from
	 *                                  the scaffold can be told apart.
	 */
	public function __construct( ViewFactory $view_factory, string $panel_title ) {
		$this->view_factory = $view_factory;
		$this->panel_title  = $panel_title;
	}

	/**
	 * Create the output of the panel.
	 *
	 * @param QM_Collector       $collector Collector of the panel.
	 * @param RegistrationReport $report    Registration history to show.
	 *
	 * @return QM_Output_Html Output of the panel.
	 */
	public function create( QM_Collector $collector, RegistrationReport $report ): QM_Output_Html {
		return new class( $collector, $report, $this->view_factory, $this->panel_title ) extends QM_Output_Html {

			/**
			 * Registration history to show.
			 */
			private RegistrationReport $report;

			/**
			 * Factory for the view of the panel.
			 */
			private ViewFactory $view_factory;

			/**
			 * Title of the panel.
			 */
			private string $panel_title;

			/**
			 * Instantiate the output.
			 *
			 * @param QM_Collector       $collector    Collector of the panel.
			 * @param RegistrationReport $report       Registration history to show.
			 * @param ViewFactory        $view_factory Factory for the view of the panel.
			 * @param string             $panel_title  Title of the panel.
			 */
			public function __construct(
				QM_Collector $collector,
				RegistrationReport $report,
				ViewFactory $view_factory,
				string $panel_title
			) {
				parent::__construct( $collector );
				$this->report       = $report;
				$this->view_factory = $view_factory;
				$this->panel_title  = $panel_title;
			}

			/**
			 * Get the title of the panel.
			 *
			 * @return string Title of the panel.
			 */
			public function name(): string {
				return $this->panel_title;
			}

			/**
			 * Render the panel.
			 *
			 * Query Monitor asks for the output once, when it prints its
			 * panels at the end of the request, so the history is complete by
			 * then. A Delayed service whose action never fired still shows as
			 * waiting for it. Reading the history changes nothing, and the
			 * view escapes what it prints.
			 *
			 * @return string HTML of the panel.
			 */
			public function get_output(): string {
				$records = $this->report->get_records();

				return $this->view_factory->create( RegistrationOutputFactory::TEMPLATE )->render(
					[
						'panel_id' => $this->collector->id(),
						'title'    => $this->panel_title,
						'records'  => $records,
						'services' => $this->get_current_states( $records ),
					]
				);
			}

			/**
			 * Derive the current state of each service from the history: its
			 * latest record, in the order the services first appear.
			 *
			 * @param RegistrationRecord[] $records Registration history.
			 *
			 * @return array<string,array{record: RegistrationRecord, transitions: int}> Latest record
			 *                                                                            and number of
			 *                                                                            records, by
			 *                                                                            service ID.
			 */
			private function get_current_states( array $records ): array {
				$services = [];

				foreach ( $records as $record ) {
					$id = $record->get_service_id();

					$services[ $id ] = [
						'record'      => $record,
						'transitions' => ( $services[ $id ]['transitions'] ?? 0 ) + 1,
					];
				}

				return $services;
			}
		};
	}
}
