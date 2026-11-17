<?php
require_once 'cors.php';
require_once 'database.php';

// Crop price rankings for the mobile app.
// Same ranking as the Crop Prices page on the website: crops ranked by the
// average of each region's latest price, in pesos per kg
// (sack = 50 kg, ton = 1000 kg; "piece" prices are skipped).
//
// GET /crop_price_rankings.php
//     ?region=Region XI (Davao Region)   optional, rank using one region only
//     &limit=10                          optional, 1-20 (default 10)

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$region = trim($_GET['region'] ?? '');
$limit  = (int) ($_GET['limit'] ?? 10);
if ($limit < 1)  { $limit = 10; }
if ($limit > 20) { $limit = 20; }

$unitToKg = ['kg' => 1.0, 'ton' => 1000.0, 'sack' => 50.0];

try {
    $db = getConnection();

    // No prices table in this database yet: answer with an empty ranking
    $hasPrices = $db->query("SHOW TABLES LIKE 'crop_prices'")->fetchColumn();
    if (!$hasPrices) {
        echo json_encode([
            'success' => true,
            'note'    => 'crop_prices table not found in this database',
            'data'    => ['region' => null, 'regions' => [], 'ranking' => []],
        ]);
        exit;
    }

    // cropInfo's key column is crop_id on the website, id in some setups
    $cropCols = $db->query('SHOW COLUMNS FROM cropInfo')->fetchAll(PDO::FETCH_COLUMN);
    $key = in_array('crop_id', $cropCols, true) ? 'crop_id' : 'id';

    $regions = $db->query('SELECT DISTINCT region FROM crop_prices ORDER BY region ASC')
        ->fetchAll(PDO::FETCH_COLUMN);

    $sql = "
        SELECT p.crop_id, p.region, p.price, p.unit, p.price_date,
               c.crop_name, c.crop_type, c.crop_description
        FROM crop_prices p
        JOIN cropInfo c ON c.$key = p.crop_id
        WHERE p.id = (
            SELECT p2.id FROM crop_prices p2
            WHERE p2.crop_id = p.crop_id AND p2.region = p.region
            ORDER BY p2.price_date DESC, p2.id DESC
            LIMIT 1
        )
    ";
    $params = [];
    if ($region !== '') {
        $sql .= ' AND p.region = ?';
        $params[] = $region;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $byCrop = [];
    foreach ($stmt->fetchAll() as $r) {
        if (!isset($unitToKg[$r['unit']])) {
            continue;
        }
        $perKg = (float) $r['price'] / $unitToKg[$r['unit']];
        $id = (int) $r['crop_id'];
        if (!isset($byCrop[$id])) {
            $byCrop[$id] = [
                'crop_id'          => $id,
                'crop_name'        => $r['crop_name'],
                'crop_type'        => $r['crop_type'],
                'crop_description' => $r['crop_description'],
                'prices'           => [],
                'sum'              => 0.0,
                'latest_date'      => $r['price_date'],
            ];
        }
        $byCrop[$id]['prices'][] = [
            'region' => $r['region'],
            'price'  => round((float) $r['price'], 2),
            'unit'   => $r['unit'],
            'date'   => $r['price_date'],
            'per_kg' => round($perKg, 2),
        ];
        $byCrop[$id]['sum'] += $perKg;
        if ($r['price_date'] > $byCrop[$id]['latest_date']) {
            $byCrop[$id]['latest_date'] = $r['price_date'];
        }
    }

    $ranking = [];
    foreach ($byCrop as $row) {
        $perKgList = array_column($row['prices'], 'per_kg');
        usort($row['prices'], fn($a, $b) => $b['per_kg'] <=> $a['per_kg']);
        $ranking[] = [
            'crop_id'          => $row['crop_id'],
            'crop_name'        => $row['crop_name'],
            'crop_type'        => $row['crop_type'],
            'crop_description' => $row['crop_description'],
            'avg_per_kg'       => round($row['sum'] / count($row['prices']), 2),
            'min_per_kg'       => min($perKgList),
            'max_per_kg'       => max($perKgList),
            'latest_date'      => $row['latest_date'],
            'region_count'     => count($row['prices']),
            'prices'           => $row['prices'],
        ];
    }

    usort($ranking, fn($a, $b) => $b['avg_per_kg'] <=> $a['avg_per_kg']);
    $ranking = array_slice($ranking, 0, $limit);
    foreach ($ranking as $i => &$item) {
        $item['rank'] = $i + 1;
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'data'    => [
            'region'  => $region !== '' ? $region : null,
            'regions' => $regions,
            'ranking' => $ranking,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('crop_price_rankings.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load price rankings']);
}
