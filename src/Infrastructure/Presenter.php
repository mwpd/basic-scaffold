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

namespace MWPD\BasicScaffold\Infrastructure;

/**
 * An object that answers the presentation queries of a template.
 *
 * A template can call methods on `$this` when the service that renders it
 * passes a presenter along:
 *
 *     $view->render( [ 'post' => $post ], $this->post_header );
 *
 * Only methods declared on an interface that extends Presenter are callable,
 * so a template sees exactly the contract its presenter publishes, and other
 * public methods of the implementation stay out of reach:
 *
 *     interface PostHeader extends Presenter {
 *         public function title(): string;
 *     }
 *
 * A string or stringable result is escaped like a context property. Other
 * values, such as booleans for a branch, are returned as they are. A presenter
 * that produces markup on purpose returns it as SafeHtml.
 *
 * A presenter is a collaborator like any other: the service that renders the
 * template receives it through its constructor. Partials inherit the
 * presenter of the template that renders them unless they are given their
 * own.
 */
interface Presenter {
}
