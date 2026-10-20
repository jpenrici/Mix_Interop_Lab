<!-- demo/public/index.php -->

<?php
header('Content-Type: application/json');

$libPath = __DIR__ . '/lib/math_engine.so'; // Linux
$ffi = FFI::cdef("
void multiply_matrices_c(const double* a, const double* b, double* result, size_t n);
", $libPath);

$action = $_GET['action'] ?? '';

if ($action === 'compute') {
    $csvFile = __DIR__ . '/../data/matrix.csv';

    if (!file_exists($csvFile)) {
        echo json_encode(['error' => 'Matrix CSV file not found.']);
        exit;
    }

    // Load CSV
    $rows = [];
    if (($handle = fopen($csvFile, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = array_map('floatval', $data);
        }
        fclose($handle);
    }

    $n = count($rows);

    // FFI
    $matrixA = $ffi->new("double[" . ($n * $n) . "]");
    $matrixB = $ffi->new("double[" . ($n * $n) . "]");
    $result  = $ffi->new("double[" . ($n * $n) . "]");

    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $matrixA[$i * $n + $j] = $rows[$i][$j];
            $matrixB[$i * $n + $j] = $rows[$i][$j];
        }
    }

    $ffi->multiply_matrices_c($matrixA, $matrixB, $result, $n);

    // Array PHP
    $output = [];
    for ($i = 0; $i < $n; $i++) {
        $row = [];
        for ($j = 0; $j < $n; $j++) {
            $row[] = $result[$i * $n + $j];
        }
        $output[] = $row;
    }

    echo json_encode(['status' => 'success', 'result' => $output]);
    exit;
}

echo json_encode(['message' => 'Mix PHP Server running. Use ?action=compute']);
