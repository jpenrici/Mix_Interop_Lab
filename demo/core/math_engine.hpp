// demo/core/math_engine.hpp

#pragma once

#include <expected>
#include <span>

namespace math_engine {

enum class Error {
    invalid_argument, // buffer size does not match the declared dimensions
    dimension_mismatch, // a_cols != b_rows
    overflow, // a dimension product does not fit in std::size_t
};

/// Returns x * y, or Error::overflow if the product does not fit in std::size_t.
[[nodiscard]] auto checked_mul(std::size_t x, std::size_t y) noexcept -> std::expected<std::size_t, Error>;

/// Computes result = A * B for row-major matrices.
///
/// A is a_rows x a_cols, B is b_rows x b_cols, result is a_rows x b_cols.
/// `result` must not overlap `a` or `b`; on success it is fully overwritten.
/// On error, `result` is left untouched.
[[nodiscard]] auto multiply_matrices(
    std::span<const double> a, std::size_t a_rows, std::size_t a_cols,
    std::span<const double> b, std::size_t b_rows, std::size_t b_cols,
    std::span<double> result) noexcept -> std::expected<void, Error>;

} // namespace math_engine
