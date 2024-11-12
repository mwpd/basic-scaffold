<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit\Exception;

use MWPD\BasicScaffold\Exception\FailedToEscapeValue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class FailedToEscapeValueTest extends TestCase {

	public function test_from_value_with_scalar(): void {
		$value  = 42;
		$result = FailedToEscapeValue::from_value( $value );

		$this->assertStringContainsString( '42', $result->getMessage() );
	}

	public function test_from_value_with_object_with_to_string(): void {
		$value = new class() {
			public function __toString() {
				return 'custom string';
			}
		};

		$result = FailedToEscapeValue::from_value( $value );

		$this->assertStringContainsString( 'custom string', $result->getMessage() );
	}

	public function test_from_value_with_non_stringable_object(): void {
		$value = new stdClass();

		$result = FailedToEscapeValue::from_value( $value );

		$this->assertStringContainsString( '{object}', $result->getMessage() );
	}

	public function test_from_value_with_array(): void {
		$value = [ 'test' ];

		$result = FailedToEscapeValue::from_value( $value );

		$this->assertStringContainsString( '{array}', $result->getMessage() );
	}
}
