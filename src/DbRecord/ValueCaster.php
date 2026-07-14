<?php

/**
 * This file is part of Piko DbRecord - Web micro framework
 *
 * @copyright 2019-2026 Sylvain PHILIP
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/db-record
 */

declare(strict_types=1);

namespace Piko\DbRecord;

use DateTime;
use JsonException;
use Stringable;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Handles value casting and SQL normalization.
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
final class ValueCaster
{
    public static function cast(mixed $value, string $type, ?int $decimalScale = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) self::toString($value),
            'bool' => self::toBool($value),
            'float' => (float) self::toString($value),
            'decimal' => self::normalizeDecimal($value, $decimalScale),
            'datetime_immutable' => $value instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable(self::toString($value)),
            'datetime_mutable' => $value instanceof DateTimeInterface
                ? DateTime::createFromInterface($value)
                : new DateTime(self::toString($value)),
            'json' => is_string($value) ? self::decodeJson($value) : (array) $value,
            default => self::toString($value),
        };
    }

    public static function normalizeForDatabase(mixed $value, string $type, ?int $decimalScale = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'decimal' => self::normalizeDecimal($value, $decimalScale),
            'datetime_immutable', 'datetime_mutable' => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : self::toString($value),
            'json' => is_string($value) ? $value : self::encodeJson($value),
            default => $value,
        };
    }

    public static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Cannot convert value to string for database cast.');
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $filtered ?? (bool) $value;
    }

    private static function normalizeDecimal(mixed $value, ?int $decimalScale): string
    {
        $raw = trim(self::toString($value));

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Invalid decimal value: ' . $raw);
        }

        if ($decimalScale === null) {
            return $raw;
        }

        return number_format((float) $raw, $decimalScale, '.', '');
    }

    /**
     * @return array<mixed>
     */
    private static function decodeJson(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid JSON value: ' . $exception->getMessage(), 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Unable to encode JSON value: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }
}
