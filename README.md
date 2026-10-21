# Project Mix: interoperability lab

Multiplication of two matrices (not necessarily square) crossing five languages:

**Perl** orchestrates → **Python** hosts the window → **HTML/JS** collects the matrices → **PHP** serves the API → **C++** does the math, bridged to PHP through **FFI** over a C ABI.

## Pipeline

```
setup.pl (Perl)
  ├─ check  → validates php, cmake, python3, the FFI extension and the .venv
  ├─ build  → CMake compiles core/ into public/lib/math_engine.so
  └─ run    → .venv/bin/python main.py

main.py (Python/PySide6)
  └─ starts `php -d ffi.enable=1 -S 127.0.0.1:8080 -t public`
     and opens http://127.0.0.1:8080/ui/index.html in a QWebEngineView window

Browser (ui/index.html + app.js)
  └─ POST /index.php?action=multiply   {"a": [[...]], "b": [[...]]}

PHP (public/index.php → src/MatrixEngine.php)
  └─ validates the matrices and calls through FFI

C ABI (core/math_engine_c.h/.cpp)
  └─ math_engine_multiply(...) → status code, no exceptions

C++ (core/math_engine.hpp/.cpp)
  └─ multiply_matrices(...) → std::expected<void, Error>
```

## Layout

```
demo/
├── setup.pl                # orchestrator: check | build | run | all | clean
├── main.py                 # PHP server + PySide6 window
├── requirements.txt        # PySide6
├── CMakeLists.txt          # produces public/lib/math_engine.so
├── core/                   # native engine
│   ├── math_engine.hpp/.cpp  # internal C++ API and extern "C" bridge
│   └── math_engine.h         # stable C ABI, consumed via FFI
├── src/
│   └── MatrixEngine.php    # FFI wrapper: validation + native call
└── public/                 # PHP server web root
    ├── index.php           # JSON endpoint
    ├── lib/                # compiled library (generated)
    └── ui/                 # front end: index.html, app.js, style.css
```

## Requirements

- Linux, GCC 12+ or Clang 16+ (C++23), CMake 3.25+
- PHP 8.1+ with the `ffi` extension
- Python 3.9+ (PySide6 is installed into `.venv` by `setup.pl`)
- Perl 5 (any recent version)

## Usage

```bash
perl setup.pl all      # check + build + run
perl setup.pl clean    # remove build/ and the compiled library
```

In the UI, type or paste matrices A and B (one row per line, values separated by commas or spaces), or use **Load CSV**. The button is only enabled when `cols(A) = rows(B)`.

## API

`POST /index.php?action=multiply` with `Content-Type: application/json`

| Status | Meaning |
|--------|---------|
| 200 | `{"status":"success","dimensions":{...},"result":[[...]]}` |
| 400 | malformed or non-rectangular matrix, or more than 1000 per dimension |
| 422 | incompatible dimensions or result outside the finite range |
| 405 / 415 | invalid method or `Content-Type` |
| 500 | engine unavailable (missing library, FFI disabled) |

