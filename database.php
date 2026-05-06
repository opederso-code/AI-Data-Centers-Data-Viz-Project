<?php
mysqli_report(MYSQLI_REPORT_OFF);

// ---------- Output buffering with gzip ----------
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

// ---------- Headers ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // 5 min cache

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- DB credentials ----------
// TODO: move these out of the web root into an env file or config above docroot
$db_host = 'webdev.iyaserver.com';
$db_port = 3306;
$db_user = 'wthoman_data_guest';
$db_pass = 'dataproject';
$db_name = 'wthoman_ai_data_center_data';

// ---------- Connect with native int/float types ----------
$conn = mysqli_init();
if (!$conn) {
    error_log('mysqli_init failed');
    http_response_code(500);
    echo json_encode(['error' => 'Database initialization failed']);
    exit;
}
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

if (!@$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port)) {
    error_log('DB connect failed: ' . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

// ---------- Helper: run query, return rows, fail cleanly ----------
function fetch_all_rows(mysqli $conn, string $sql, string $label): array {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("Query failed [$label]: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Query failed', 'table' => $label]);
        exit;
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

function get_existing_columns(mysqli $conn, string $table): array {
    $safeTable = str_replace('`', '``', $table);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}`");
    if (!$result) return [];
    $cols = [];
    while ($row = $result->fetch_assoc()) {
        if (isset($row['Field'])) $cols[] = $row['Field'];
    }
    $result->free();
    return $cols;
}

// ---------- Optional table filter: /database.php?table=gpu_specs ----------
// If ?table is given, only that table is returned. Otherwise all five.
$only = isset($_GET['table']) ? trim($_GET['table']) : null;
$allowed = ['gpu_specs', 'datacenter_components', 'gpu_clusters', 'regional_data_annex', 'world_data_annex'];
if ($only !== null && !in_array($only, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown table', 'allowed' => $allowed]);
    exit;
}

$response = [];

// ---------- Table 1: gpu_specs ----------
if ($only === null || $only === 'gpu_specs') {
    $sql = "
        SELECT
            `manufacturer`,
            `productName`,
            `releaseYear`,
            `memSize`,
            `memBusWidth`,
            `gpuClock`,
            `memClock`,
            `unifiedShader`,
            `tmu`,
            `rop`,
            `pixelShader`,
            `vertexShader`,
            `igp`,
            `bus`,
            `memType`,
            `gpuChip`
        FROM `gpu_specs`
        WHERE `memSize` IS NOT NULL
          AND `unifiedShader` IS NOT NULL
          AND `gpuClock` IS NOT NULL
          AND `memClock` IS NOT NULL
    ";
    $response['gpu_specs'] = fetch_all_rows($conn, $sql, 'gpu_specs');
}

// ---------- Table 2: datacenter_components ----------
if ($only === null || $only === 'datacenter_components') {
    $sql = "
        SELECT
            `year`,
            `component`,
            `consumption_twh`
        FROM `datacenter_components`
        ORDER BY `year` ASC, `component` ASC
    ";
    $response['datacenter_components'] = fetch_all_rows($conn, $sql, 'datacenter_components');
}

// ---------- Table 3: gpu_clusters ----------
if ($only === null || $only === 'gpu_clusters') {
    $requestedCols = [
        'name',
        'status',
        'certainty',
        'single_cluster',
        'h100_equivalents',
        'chip_type_primary',
        'chip_quantity_primary',
        'country',
        'owner',
        'first_operational_date',
        'sector',
        'power_capacity_mw',
        'location',
        'builds_upon',
        'superseded_by',
        'possible_duplicate',
        'possible_duplicate_of',
        'chip_type_secondary',
        'chip_quantity_secondary',
        'total_number_of_ai_chips',
        'gpu_supplier_primary',
        'gpu_supplier_secondary',
        'include_in_standard_analysis',
        'exclude',
        'rank_when_first_operational',
        'energy_efficiency',
        'decommissioned_date',
        'largest_existing_cluster_when_first_operational',
        'pct_of_largest_cluster_when_first_operational'
    ];

    $existing = array_flip(get_existing_columns($conn, 'gpu_clusters'));
    $selected = array_values(array_filter($requestedCols, fn($c) => isset($existing[$c])));
    $selectSql = '*';
    if (!empty($selected)) {
        $selectSql = implode(",\n            ", array_map(fn($c) => "`{$c}`", $selected));
    }

    $sql = "
        SELECT
            {$selectSql}
        FROM `gpu_clusters`
    ";
    $response['gpu_clusters'] = fetch_all_rows($conn, $sql, 'gpu_clusters');
}

// ---------- Table 4: regional_data_annex ----------
if ($only === null || $only === 'regional_data_annex') {
    $sql = "
        SELECT
            `metric`,
            `unit`,
            `region`,
            `year`,
            `scenario`,
            `value`
        FROM `regional_data_annex`
    ";
    $response['regional_data_annex'] = fetch_all_rows($conn, $sql, 'regional_data_annex');
}

// ---------- Table 5: world_data_annex ----------
if ($only === null || $only === 'world_data_annex') {
    $sql = "
        SELECT
            `metric`,
            `segment`,
            `base_2020`,
            `base_2023`,
            `base_2024`,
            `liftoff_2030`,
            `liftoff_2035`,
            `high_efficiency_2030`,
            `high_efficiency_2035`,
            `headwinds_2030`,
            `headwinds_2035`
        FROM `world_data_annex`
    ";
    $response['world_data_annex'] = fetch_all_rows($conn, $sql, 'world_data_annex');
}

// ---------- Output ----------
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
ob_end_flush();
