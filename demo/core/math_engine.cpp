// demo/core/math_engine.cpp

#include "math_engine.hpp"
#include "math_engine.h"

#include <algorithm>
#include <expected>
#include <limits>

namespace math_engine {

auto checked_mul(std::size_t x, std::size_t y) noexcept -> std::expected<std::size_t, Error>
{
    if (x != 0 && y > std::numeric_limits<std::size_t>::max() / x) {
        return std::unexpected(Error::overflow);
    }
    return x * y;
}

auto multiply_matrices(
    std::span<const double> a, std::size_t a_rows, std::size_t a_cols,
    std::span<const double> b, std::size_t b_rows, std::size_t b_cols,
    std::span<double> result) noexcept -> std::expected<void, Error>
{
    if (a_cols != b_rows) {
        return std::unexpected(Error::dimension_mismatch);
    }

    const auto a_size = checked_mul(a_rows, a_cols);
    const auto b_size = checked_mul(b_rows, b_cols);
    const auto r_size = checked_mul(a_rows, b_cols);
    if (!a_size)
        return std::unexpected(a_size.error());
    if (!b_size)
        return std::unexpected(b_size.error());
    if (!r_size)
        return std::unexpected(r_size.error());

    if (a.size() != *a_size || b.size() != *b_size || result.size() != *r_size) {
        return std::unexpected(Error::invalid_argument);
    }

    std::ranges::fill(result, 0.0);

    // i-k-j order keeps the inner loop streaming through contiguous rows of B and result.
    for (std::size_t i = 0; i < a_rows; ++i) {
        for (std::size_t k = 0; k < a_cols; ++k) {
            const double a_ik = a[i * a_cols + k];
            for (std::size_t j = 0; j < b_cols; ++j) {
                result[i * b_cols + j] += a_ik * b[k * b_cols + j];
            }
        }
    }
    return {};
}

} // namespace math_engine

namespace {

auto to_status(math_engine::Error error) noexcept -> int
{
    switch (error) {
    case math_engine::Error::invalid_argument:
        return MATH_ENGINE_INVALID_ARGUMENT;
    case math_engine::Error::dimension_mismatch:
        return MATH_ENGINE_DIMENSION_MISMATCH;
    case math_engine::Error::overflow:
        return MATH_ENGINE_OVERFLOW;
    }
    return MATH_ENGINE_INTERNAL_ERROR;
}

} // namespace

extern "C" {

int math_engine_multiply(const double* a, size_t a_rows, size_t a_cols,
    const double* b, size_t b_rows, size_t b_cols,
    double* result)
{
    if (a == nullptr || b == nullptr || result == nullptr) {
        return MATH_ENGINE_NULL_POINTER;
    }
    if (a_rows == 0 || a_cols == 0 || b_rows == 0 || b_cols == 0) {
        return MATH_ENGINE_INVALID_ARGUMENT;
    }

    // Sizes are validated again inside multiply_matrices; here we only need them to build the spans.
    const auto a_size = math_engine::checked_mul(a_rows, a_cols);
    const auto b_size = math_engine::checked_mul(b_rows, b_cols);
    const auto r_size = math_engine::checked_mul(a_rows, b_cols);
    if (!a_size)
        return to_status(a_size.error());
    if (!b_size)
        return to_status(b_size.error());
    if (!r_size)
        return to_status(r_size.error());

    const auto outcome = math_engine::multiply_matrices(
        { a, *a_size }, a_rows, a_cols,
        { b, *b_size }, b_rows, b_cols,
        { result, *r_size });

    return outcome ? MATH_ENGINE_OK : to_status(outcome.error());
}

const char* math_engine_status_message(int status)
{
    switch (status) {
    case MATH_ENGINE_OK:
        return "OK";
    case MATH_ENGINE_NULL_POINTER:
        return "Null pointer argument.";
    case MATH_ENGINE_INVALID_ARGUMENT:
        return "Invalid argument.";
    case MATH_ENGINE_DIMENSION_MISMATCH:
        return "Incompatible matrix dimensions.";
    case MATH_ENGINE_OVERFLOW:
        return "Matrix size overflow.";
    case MATH_ENGINE_INTERNAL_ERROR:
        return "Internal engine error.";
    default:
        return "Unknown status code.";
    }
}

} // extern "C"
