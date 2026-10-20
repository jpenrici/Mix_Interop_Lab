# demo/CMakeLists.txt

cmake_minimum_required(VERSION 3.25 FATAL_ERROR)

project(math_engine LANGUAGES C CXX)

set(CMAKE_CXX_STANDARD 23)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

add_library(math_engine SHARED core/math_engine.cpp core/math_engine.hpp
                               core/math_engine.h)

set_target_properties(
  math_engine
  PROPERTIES LIBRARY_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/public/lib"
             RUNTIME_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/public/lib")
