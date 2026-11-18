<?php
/**
 * crop_rankings.php  (put this next to crops.php / fertilizers.php in the API)
 * GET /crop_rankings.php?limit=5&region=Region%20XI%20(Davao%20Region)
 * Returns the top crops by average market price (pesos per kg) with each
 * crop's soil needs, prices by region and matching fertilizers.
 * Self-contained: reads the same DB_* environment variables as the rest of the API.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
if (defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
    $options[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
} elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO(
        'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';port=' . (getenv('DB_PORT') ?: '3306')
            . ';dbname=' . (getenv('DB_NAME') ?: 'defaultdb') . ';charset=utf8mb4',
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        $options
    );

    $limit  = max(1, min(20, (int) ($_GET['limit'] ?? 5)));
    $region = trim($_GET['region'] ?? '');
    $unitToKg = ['kg' => 1.0, 'ton' => 1000.0, 'sack' => 50.0]; // a sack counts as 50 kg; "piece" can't be compared

    $sql = '
        SELECT p.crop_id, p.region, p.price, p.unit, p.price_date,
               c.crop_name, c.crop_type, c.crop_description, c.pic1_url,
               c.nitrogen_min, c.nitrogen_max, c.phosphorus_min, c.phosphorus_max,
               c.potassium_min, c.potassium_max, c.soil_ph_min, c.soil_ph_max,
               c.moisture_min, c.moisture_max
        FROM crop_prices p
        JOIN cropInfo c ON c.crop_id = p.crop_id
        WHERE p.id = (
            SELECT p2.id FROM crop_prices p2
            WHERE p2.crop_id = p.crop_id AND p2.region = p.region
            ORDER BY p2.price_date DESC, p2.id DESC LIMIT 1
        )';
    $params = [];
    if ($region !== '') {
        $sql .= ' AND p.region = ?';
        $params[] = $region;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $byCrop = [];
    foreach ($stmt->fetchAll() as $r) {
        if (!isset($unitToKg[$r['unit']])) continue;
        $perKg = (float) $r['price'] / $unitToKg[$r['unit']];
        $id = (int) $r['crop_id'];
        if (!isset($byCrop[$id])) $byCrop[$id] = ['crop' => $r, 'prices' => []];
        $byCrop[$id]['prices'][] = [
            'region' => $r['region'], 'price' => (float) $r['price'], 'unit' => $r['unit'],
            'price_date' => $r['price_date'], 'per_kg' => round($perKg, 2),
        ];
    }

    $ranking = [];
    foreach ($byCrop as $id => $row) {
        $perKgs = array_column($row['prices'], 'per_kg');
        usort($row['prices'], fn($a, $b) => $b['per_kg'] <=> $a['per_kg']);
        $c = $row['crop'];
        $ranking[] = [
            'crop_id' => $id,
            'crop_name' => $c['crop_name'],
            'crop_type' => $c['crop_type'],
            'crop_description' => $c['crop_description'],
            'pic1_url' => $c['pic1_url'],
            'avg_per_kg' => round(array_sum($perKgs) / count($perKgs), 2),
            'min_per_kg' => min($perKgs),
            'max_per_kg' => max($perKgs),
            'region_count' => count($perKgs),
            'prices' => array_slice($row['prices'], 0, 5),
            'soil' => [
                'nitrogen_min' => $c['nitrogen_min'], 'nitrogen_max' => $c['nitrogen_max'],
                'phosphorus_min' => $c['phosphorus_min'], 'phosphorus_max' => $c['phosphorus_max'],
                'potassium_min' => $c['potassium_min'], 'potassium_max' => $c['potassium_max'],
                'soil_ph_min' => $c['soil_ph_min'], 'soil_ph_max' => $c['soil_ph_max'],
                'moisture_min' => $c['moisture_min'], 'moisture_max' => $c['moisture_max'],
            ],
            'fertilizers' => [],
        ];
    }
    usort($ranking, fn($a, $b) => $b['avg_per_kg'] <=> $a['avg_per_kg']);
    $ranking = array_slice($ranking, 0, $limit);

    $types = array_values(array_unique(array_filter(array_column($ranking, 'crop_type'))));
    $fertsByType = [];
    if ($types) {
        $in = implode(',', array_fill(0, count($types), '?'));
        $stmt = $pdo->prepare("
            SELECT fertilizer_name, fertilizer_type, crop_type, nitrogen_content, phosphorus_content,
                   potassium_content, application_method, application_time
            FROM fertilizer WHERE crop_type IN ($in) ORDER BY fertilizer_name ASC");
        $stmt->execute($types);
        foreach ($stmt->fetchAll() as $f) $fertsByType[$f['crop_type']][] = $f;
    }
    foreach ($ranking as $i => &$r) {
        $r['rank'] = $i + 1;
        $r['fertilizers'] = array_slice($fertsByType[$r['crop_type']] ?? [], 0, 3);
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $ranking], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('crop_rankings.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load rankings']);
}
