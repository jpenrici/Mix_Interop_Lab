// demo/core/math_engine.cpp

#include "math_engine.hpp"

#include <stdexcept>

void MathEngine::multiply_matrices(
    std::span<const double> a, size_t a_rows, size_t a_cols,
    std::span<const double> b, size_t b_rows, size_t b_cols,
    std::vector<double>& result)
{
    if (a_cols != b_rows) {
        throw std::invalid_argument("Incompatible matrix dimensions for multiplication.");
    }

    result.assign(a_rows * b_cols, 0.0);

    for (size_t i = 0; i < a_rows; ++i) {
        for (size_t k = 0; k < a_cols; ++k) {
            double a_val = a[i * a_cols + k];
            for (size_t j = 0; j < b_cols; ++j) {
                result[i * b_cols + j] += a_val * b[k * b_cols + j];
            }
        }
    }
}
