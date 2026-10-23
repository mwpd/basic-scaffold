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

namespace MWPD\BasicScaffold\Tests\Fixture\View;

use MWPD\BasicScaffold\Infrastructure\Presenter;
use MWPD\BasicScaffold\Infrastructure\View\SafeHtml;

/**
 * A presenter interface with one method for each kind of result.
 */
interface Greeting extends Presenter {

	public function greeting( string $name, string $punctuation = '!' ): string;

	public function is_formal(): bool;

	public function emphasis(): SafeHtml;

	public function label(): Label;

	public function count_guests(): int;

	/**
	 * @return list<string>
	 */
	public function guest_list(): array;

	public function note(): ?string;

	public function failing(): string;
}
