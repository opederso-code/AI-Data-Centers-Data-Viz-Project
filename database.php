<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db_host = 'webdev.iyaserver.com';
$db_port = 3306;
$db_user = 'wthoman_data_guest';
$db_pass = 'dataproject';
$db_name = 'wthoman_ai_data_center_data';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $conn->connect_error
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

function normalize_name($name) {
    $name = strtolower(trim((string)$name));
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return trim($name, '_');
}

function safe_float($value) {
    return is_numeric($value) ? (float)$value : null;
}

function safe_int($value) {
    return is_numeric($value) ? (int)$value : null;
}

function fetch_rows($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        return [
            'error' => true,
            'message' => $conn->error,
            'rows' => []
        ];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return [
        'error' => false,
        'rows' => $rows
    ];
}

/*
|--------------------------------------------------------------------------
| 1) gpu_specs
|--------------------------------------------------------------------------
*/
$specs_sql = "
    SELECT
        manufacturer,
        productName,
        releaseYear,
        memSize,
        memBusWidth,
        gpuClock,
        memClock,
        unifiedShader,
        tmu,
        rop,
        pixelShader,
        vertexShader,
        igp,
        bus,
        memType,
        gpuChip
    FROM gpu_specs
    WHERE memSize IS NOT NULL
      AND unifiedShader IS NOT NULL
      AND gpuClock IS NOT NULL
      AND memClock IS NOT NULL
";

$specs_result = fetch_rows($conn, $specs_sql);
if ($specs_result['error']) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Query failed on gpu_specs',
        'details' => $specs_result['message']
    ]);
    exit;
}

$specs = array_map(function ($row) {
    return [
        'source' => 'gpu_specs',
        'manufacturer' => $row['manufacturer'],
        'name' => $row['productName'],
        'releaseYear' => safe_int($row['releaseYear']),
        'memSize' => safe_float($row['memSize']),
        'memBusWidth' => safe_int($row['memBusWidth']),
        'gpuClock' => safe_int($row['gpuClock']),
        'memClock' => safe_int($row['memClock']),
        'unifiedShader' => safe_int($row['unifiedShader']),
        'tmu' => safe_int($row['tmu']),
        'rop' => safe_int($row['rop']),
        'pixelShader' => safe_int($row['pixelShader']),
        'vertexShader' => safe_int($row['vertexShader']),
        'igp' => $row['igp'],
        'bus' => $row['bus'],
        'memType' => $row['memType'],
        'gpuChip' => $row['gpuChip'],
        'gpu_key' => normalize_name($row['productName'])
    ];
}, $specs_result['rows']);

/*
|--------------------------------------------------------------------------
| 2) gpu_benchmarks
|--------------------------------------------------------------------------
*/
$benchmarks_sql = "
    SELECT
        gpuName,
        G3Dmark,
        G2Dmark,
        price,
        gpuValue,
        TDP,
        powerPerformance,
        testDate,
        category
    FROM gpu_benchmarks
";

$benchmarks_result = fetch_rows($conn, $benchmarks_sql);
if ($benchmarks_result['error']) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Query failed on gpu_benchmarks',
        'details' => $benchmarks_result['message']
    ]);
    exit;
}

$benchmarks = array_map(function ($row) {
    return [
        'source' => 'gpu_benchmarks',
        'manufacturer' => null,
        'name' => $row['gpuName'],
        'G3Dmark' => safe_int($row['G3Dmark']),
        'G2Dmark' => safe_int($row['G2Dmark']),
        'price' => safe_float($row['price']),
        'gpuValue' => safe_float($row['gpuValue']),
        'TDP' => safe_int($row['TDP']),
        'powerPerformance' => safe_float($row['powerPerformance']),
        'testDate' => safe_int($row['testDate']),
        'category' => $row['category'],
        'gpu_key' => normalize_name($row['gpuName'])
    ];
}, $benchmarks_result['rows']);

/*
|--------------------------------------------------------------------------
| 3) gpu_scores_graphicsapis_in
|--------------------------------------------------------------------------
*/
$scores_sql = "
    SELECT
        Manufacturer,
        Device,
        CUDA,
        Metal,
        OpenCL,
        Vulkan
    FROM gpu_scores_graphicsapis_in
";

$scores_result = fetch_rows($conn, $scores_sql);
if ($scores_result['error']) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Query failed on gpu_scores_graphicsapis_in',
        'details' => $scores_result['message']
    ]);
    exit;
}

$scores = array_map(function ($row) {
    return [
        'source' => 'gpu_scores_graphicsapis_in',
        'manufacturer' => $row['Manufacturer'],
        'name' => $row['Device'],
        'CUDA' => safe_int($row['CUDA']),
        'Metal' => safe_int($row['Metal']),
        'OpenCL' => safe_int($row['OpenCL']),
        'Vulkan' => safe_int($row['Vulkan']),
        'gpu_key' => normalize_name($row['Device'])
    ];
}, $scores_result['rows']);

/*
|--------------------------------------------------------------------------
| Build combined dataset
| - Uses GPU name as the primary match key
| - Keeps nested data from each table
|--------------------------------------------------------------------------
*/
$combined = [];

$ensure_base = function ($key, $name = null, $manufacturer = null) use (&$combined) {
    if (!isset($combined[$key])) {
        $combined[$key] = [
            'gpu_key' => $key,
            'name' => $name,
            'manufacturer' => $manufacturer,
            'specs' => null,
            'benchmark' => null,
            'api_scores' => null
        ];
    } else {
        if ($combined[$key]['name'] === null && $name !== null) {
            $combined[$key]['name'] = $name;
        }
        if ($combined[$key]['manufacturer'] === null && $manufacturer !== null) {
            $combined[$key]['manufacturer'] = $manufacturer;
        }
    }
};

foreach ($specs as $row) {
    $key = $row['gpu_key'];
    $ensure_base($key, $row['name'], $row['manufacturer']);
    $combined[$key]['specs'] = $row;
}

foreach ($benchmarks as $row) {
    $key = $row['gpu_key'];
    $ensure_base($key, $row['name'], $row['manufacturer']);
    $combined[$key]['benchmark'] = $row;
}

foreach ($scores as $row) {
    $key = $row['gpu_key'];
    $ensure_base($key, $row['name'], $row['manufacturer']);
    $combined[$key]['api_scores'] = $row;
}

/*
|--------------------------------------------------------------------------
| Final response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'meta' => [
        'specs_count' => count($specs),
        'benchmarks_count' => count($benchmarks),
        'scores_count' => count($scores),
        'combined_count' => count($combined_out)
    ],
    'specs' => $specs,
    'benchmarks' => $benchmarks,
    'scores' => $scores,
    'combined' => $combined_out
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
?>