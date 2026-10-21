<?php

declare(strict_types=1);

namespace Demo;

use DomainException;
use FFI;
use InvalidArgumentException;
use RuntimeException;

/**
 * Thin, validated PHP wrapper around the native math engine (C ABI via FFI).
 */
final class MatrixEngine
{
    /** Maximum rows or columns accepted per matrix (bounds memory and CPU). */
    public const MAX_DIMENSION = 1000;

    private const DOUBLE_BYTES = 8;
    private const STATUS_DIMENSION_MISMATCH = 3;

    private const CDEF = <<<'C'
        int math_engine_multiply(
            const double* a, size_t a_rows, size_t a_cols,
            const double* b, size_t b_rows, size_t b_cols,
            double* result);
        const char* math_engine_status_message(int status);
        C;

    private readonly FFI $ffi;

    public function __construct(string $libraryPath)
    {
        if (!extension_loaded('ffi')) {
            throw new RuntimeException('The PHP FFI extension is not available.');
        }
        if (!is_file($libraryPath)) {
            throw new RuntimeException('Native library not found. Build it first.');
        }

        try {
            $this->ffi = FFI::cdef(self::CDEF, $libraryPath);
        } catch (FFI\Exception $e) {
            throw new RuntimeException('Failed to load the native library.', 0, $e);
        }
    }

    /** Platform-specific path of the shared library inside $libDir. */
    public static function libraryPath(string $libDir): string
    {
        $extension = match (PHP_OS_FAMILY) {
            'Windows' => 'dll',
            'Darwin'  => 'dylib',
            default   => 'so',
        };

        return $libDir . DIRECTORY_SEPARATOR . 'math_engine.' . $extension;
    }

    /**
     * @param list<list<int|float>> $a  m x n matrix
     * @param list<list<int|float>> $b  n x p matrix
     * @return list<list<float>>        m x p matrix
     *
     * @throws InvalidArgumentException malformed or oversized input
     * @throws DomainException          incompatible shapes, or non-finite result
     * @throws RuntimeException         native engine failure
     */
    public function multiply(array $a, array $b): array
    {
        [$aRows, $aCols] = self::shape($a, 'A');
        [$bRows, $bCols] = self::shape($b, 'B');

        if ($aCols !== $bRows) {
            throw new DomainException(
                "Cannot multiply: A is {$aRows}x{$aCols} and B is {$bRows}x{$bCols} "
                . "(columns of A must equal rows of B)."
            );
        }

        $resultCount = $aRows * $bCols;
        $result = $this->ffi->new("double[{$resultCount}]");

        $status = $this->ffi->math_engine_multiply(
            self::toBuffer($this->ffi, $a, $aRows * $aCols), $aRows, $aCols,
            self::toBuffer($this->ffi, $b, $bRows * $bCols), $bRows, $bCols,
            $result
        );

        if ($status !== 0) {
            $message = FFI::string($this->ffi->math_engine_status_message($status));
            throw $status === self::STATUS_DIMENSION_MISMATCH
                ? new DomainException($message)
                : new RuntimeException($message);
        }

        /** @var array<int, float> $flat */
        $flat = unpack('d*', FFI::string($result, $resultCount * self::DOUBLE_BYTES));
        foreach ($flat as $value) {
            if (!is_finite($value)) {
                throw new DomainException('The result contains values outside the finite range.');
            }
        }

        return array_chunk(array_values($flat), $bCols);
    }

    /**
     * Validates that $matrix is a non-empty, rectangular list of numeric rows.
     *
     * @return array{int, int} [rows, cols]
     */
    private static function shape(mixed $matrix, string $name): array
    {
        if (!is_array($matrix) || $matrix === [] || !array_is_list($matrix)) {
            throw new InvalidArgumentException("Matrix {$name} must be a non-empty list of rows.");
        }

        $rows = count($matrix);
        $cols = null;

        foreach ($matrix as $i => $row) {
            $line = $i + 1;

            if (!is_array($row) || $row === [] || !array_is_list($row)) {
                throw new InvalidArgumentException("Matrix {$name}, row {$line}: expected a non-empty list of numbers.");
            }

            $cols ??= count($row);
            if (count($row) !== $cols) {
                throw new InvalidArgumentException(
                    "Matrix {$name}, row {$line}: has " . count($row) . " columns, expected {$cols}."
                );
            }

            foreach ($row as $j => $value) {
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                    throw new InvalidArgumentException(
                        "Matrix {$name}, row {$line}, column " . ($j + 1) . ': expected a finite number.'
                    );
                }
            }
        }

        if ($rows > self::MAX_DIMENSION || $cols > self::MAX_DIMENSION) {
            throw new InvalidArgumentException(
                "Matrix {$name} is {$rows}x{$cols}; each dimension is limited to " . self::MAX_DIMENSION . '.'
            );
        }

        return [$rows, $cols];
    }

    /**
     * Packs a validated matrix into a row-major native double[] buffer.
     *
     * @param list<list<int|float>> $matrix
     */
    private static function toBuffer(FFI $ffi, array $matrix, int $count): FFI\CData
    {
        $bytes = '';
        foreach ($matrix as $row) {
            $bytes .= pack('d*', ...$row);
        }

        $buffer = $ffi->new("double[{$count}]");
        FFI::memcpy($buffer, $bytes, $count * self::DOUBLE_BYTES);

        return $buffer;
    }
}
