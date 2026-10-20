// demo/core/math_engine.hpp

#pragma once

#include <span>
#include <vector>

class MathEngine {

public:
    static void multiply_matrices(
        std::span<const double> a, size_t a_rows, size_t a_cols,
        std::span<const double> b, size_t b_rows, size_t b_cols,
        std::vector<double>& result);
};
