<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db_host = 'webdev.iyaserver.com';
$db_port = 3306;
$db_user = 'wthoman_data_guest';
$db_pass = 'dataproject';
$db_name = 'wthoman_ai_data_center_data';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$conn->set_charset('utf8mb4');

$response = [];

// ---------- Table 1: gpu_specs ----------
$sql_gpu_specs = "
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

$result = $conn->query($sql_gpu_specs);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: gpu_specs', 'detail' => $conn->error]);
    exit;
}
$gpu_specs = [];
while ($row = $result->fetch_assoc()) {
    $gpu_specs[] = $row;
}
$response['gpu_specs'] = $gpu_specs;

// ---------- Table 2: datacenter_components ----------
$sql_components = "
    SELECT
        `year`,
        `component`,
        `consumption_twh`
    FROM `datacenter_components`
    ORDER BY `year` ASC, `component` ASC
";

$result = $conn->query($sql_components);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: datacenter_components', 'detail' => $conn->error]);
    exit;
}
$components = [];
while ($row = $result->fetch_assoc()) {
    $components[] = $row;
}
$response['datacenter_components'] = $components;

// ---------- Table 3: gpu_clusters ----------
$sql_clusters = "
    SELECT
        `name`,
        `status`,
        `certainty`,
        `single_cluster`,
        `h100_equivalents`,
        `chip_type_primary`,
        `chip_quantity_primary`,
        `country`,
        `owner`,
        `first_operational_date`,
        `note`,
        `sector`,
        `power_capacity_mw`,
        `location`,
        `builds_upon`,
        `superseded_by`,
        `possible_duplicate`,
        `possible_duplicate_of`,
        `chip_type_secondary`,
        `chip_quantity_secondary`,
        `total_number_of_ai_chips`,
        `gpu_supplier_primary`,
        `gpu_supplier_secondary`,
        `include_in_standard_analysis`,
        `exclude`,
        `rank_when_first_operational`,
        `energy_efficiency`,
        `noteworthy`,
        `decommissioned_date`,
        `largest_existing_cluster_when_first_operational`,
        `pct_of_largest_cluster_when_first_operational`,
        `sources`
    FROM `gpu_clusters`
";

$result = $conn->query($sql_clusters);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: gpu_clusters', 'detail' => $conn->error]);
    exit;
}
$clusters = [];
while ($row = $result->fetch_assoc()) {
    $clusters[] = $row;
}
$response['gpu_clusters'] = $clusters;

// ---------- Table 4: regional_data_annex ----------
$sql_regional = "
    SELECT
        `metric`,
        `unit`,
        `region`,
        `year`,
        `scenario`,
        `value`
    FROM `regional_data_annex`
";

$result = $conn->query($sql_regional);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: regional_data_annex', 'detail' => $conn->error]);
    exit;
}
$regional = [];
while ($row = $result->fetch_assoc()) {
    $regional[] = $row;
}
$response['regional_data_annex'] = $regional;

// ---------- Table 5: world_data_annex ----------
$sql_world = "
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

$result = $conn->query($sql_world);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: world_data_annex', 'detail' => $conn->error]);
    exit;
}
$world = [];
while ($row = $result->fetch_assoc()) {
    $world[] = $row;
}
$response['world_data_annex'] = $world;

// ---------- Output ----------
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();
?>