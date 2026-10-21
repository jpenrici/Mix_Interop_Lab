/* demo/core/math_engine.h
 *
 * Stable C ABI of the math engine, meant for FFI consumers (PHP FFI, ctypes, ...).
 */

#ifndef MATH_ENGINE_C_H
#define MATH_ENGINE_C_H

#include <stddef.h>

#if defined(_WIN32)
#ifdef MATH_ENGINE_BUILD
#define MATH_ENGINE_API __declspec(dllexport)
#else
#define MATH_ENGINE_API __declspec(dllimport)
#endif
#elif defined(__GNUC__)
#define MATH_ENGINE_API __attribute__((visibility("default")))
#else
#define MATH_ENGINE_API
#endif

#ifdef __cplusplus
extern "C" {
#endif

typedef enum math_engine_status {
    MATH_ENGINE_OK = 0,
    MATH_ENGINE_NULL_POINTER = 1,
    MATH_ENGINE_INVALID_ARGUMENT = 2,
    MATH_ENGINE_DIMENSION_MISMATCH = 3,
    MATH_ENGINE_OVERFLOW = 4,
    MATH_ENGINE_INTERNAL_ERROR = 5
} math_engine_status;

/* Computes result = A * B for row-major matrices of doubles.
 *
 *   a:      a_rows x a_cols
 *   b:      b_rows x b_cols   (b_rows must equal a_cols)
 *   result: a_rows x b_cols   (caller-allocated, must not overlap a or b)
 *
 * Returns MATH_ENGINE_OK (0) on success, or a math_engine_status error code.
 */
MATH_ENGINE_API int math_engine_multiply(
    const double* a, size_t a_rows, size_t a_cols,
    const double* b, size_t b_rows, size_t b_cols,
    double* result);

/* Returns a static, human-readable description of a status code. Never NULL. */
MATH_ENGINE_API const char* math_engine_status_message(int status);

#ifdef __cplusplus
}
#endif

#endif /* MATH_ENGINE_C_H */
