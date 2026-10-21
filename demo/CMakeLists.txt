# demo/CMakeLists.txt

cmake_minimum_required(VERSION 3.25 FATAL_ERROR)

project(
  math_engine
  VERSION 1.0.0
  LANGUAGES CXX)

if(NOT CMAKE_BUILD_TYPE AND NOT CMAKE_CONFIGURATION_TYPES)
  set(CMAKE_BUILD_TYPE
      Release
      CACHE STRING "Build type" FORCE)
endif()

add_library(math_engine SHARED core/math_engine.cpp)

target_include_directories(math_engine PUBLIC core)
target_compile_features(math_engine PUBLIC cxx_std_23)

target_compile_definitions(math_engine PRIVATE MATH_ENGINE_BUILD)

if(MSVC)
  target_compile_options(math_engine PRIVATE /W4 /permissive-)
else()
  target_compile_options(math_engine PRIVATE -Wall -Wextra -Wpedantic)
endif()

# Only symbols marked MATH_ENGINE_API are exported from the shared library.
# PREFIX "" produces `math_engine.so` (not `libmath_engine.so`), which is the
# name the PHP loader expects.
set_target_properties(
  math_engine
  PROPERTIES CXX_EXTENSIONS OFF
             CXX_VISIBILITY_PRESET hidden
             VISIBILITY_INLINES_HIDDEN ON
             PREFIX ""
             LIBRARY_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/public/lib"
             RUNTIME_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/public/lib")
