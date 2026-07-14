<?php

namespace Piko\Tests;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Piko\DbRecord\ValueCaster;

class ValueCasterTest extends TestCase
{
    public function testCastDateTimeImmutableFromDateTimeInterfaceReturnsImmutableDateTime(): void
    {
        $mutable = new DateTime('2026-02-03 04:05:06');

        $result = ValueCaster::cast($mutable, 'datetime_immutable');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2026-02-03 04:05:06', $result->format('Y-m-d H:i:s'));
    }

    public function testCastDateTimeMutableFromDateTimeInterfaceReturnsMutableDateTime(): void
    {
        $immutable = new DateTimeImmutable('2026-01-01 12:34:56');

        $result = ValueCaster::cast($immutable, 'datetime_mutable');

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertSame('2026-01-01 12:34:56', $result->format('Y-m-d H:i:s'));
    }

    public function testCastJsonFromNonStringCastsToArray(): void
    {
        $result = ValueCaster::cast(['a' => 1, 'b' => 2], 'json');

        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testToBoolFallsBackToPhpBoolCastWhenFilterCannotDecide(): void
    {
        $this->assertTrue(ValueCaster::toBool('definitely-not-a-boolean'));
    }

    public function testCastDecimalWithoutScaleReturnsTrimmedRawValue(): void
    {
        $result = ValueCaster::cast('  123.4500  ', 'decimal');

        $this->assertSame('123.4500', $result);
    }

    public function testCastJsonWithScalarJsonReturnsEmptyArray(): void
    {
        $result = ValueCaster::cast('true', 'json');

        $this->assertSame([], $result);
    }

    public function testNormalizeForDatabaseJsonThrowsOnUnsupportedValue(): void
    {
        $resource = fopen('php://memory', 'r');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Unable to encode JSON value:');

            ValueCaster::normalizeForDatabase($resource, 'json');
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testToStringThrowsForUnsupportedValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert value to string for database cast.');

        ValueCaster::toString(new \stdClass());
    }

    public function testCastDecimalThrowsForInvalidNumericValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decimal value: not-a-number');

        ValueCaster::cast('not-a-number', 'decimal');
    }

    public function testCastJsonThrowsForMalformedJsonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON value:');

        ValueCaster::cast('{invalid', 'json');
    }
}
