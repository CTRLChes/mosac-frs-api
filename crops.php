<?php
require_once 'cors.php';
require_once 'database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getCrops();
        break;
    case 'POST':
        createCrop();
        break;
    case 'PUT':
        updateCrop();
        break;
    case 'DELETE':
        deleteCrop();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// GET all crops or single crop by id
function getCrops() {
    $db = getConnection();

    if (isset($_GET['id'])) {
        $stmt = $db->prepare('SELECT * FROM cropInfo WHERE id = ?');
        $stmt->execute([$_GET['id']]);
        $crop = $stmt->fetch();

        if (!$crop) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Crop not found']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $crop]);
    } else {
        $stmt = $db->query('SELECT * FROM cropInfo ORDER BY crop_name ASC');
        $crops = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $crops]);
    }
}

// POST create new crop
function createCrop() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        return;
    }

    $required = ['crop_name', 'crop_type', 'crop_description', 'pic1_url'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    $db = getConnection();
    $stmt = $db->prepare('
        INSERT INTO cropInfo (
            crop_name, crop_type, crop_description,
            nitrogen_min, nitrogen_max,
            phosphorus_min, phosphorus_max,
            potassium_min, potassium_max,
            soil_ph_min, soil_ph_max,
            moisture_min, moisture_max,
            pic1_url, pic2_url, pic3_url, pic4_url, pic5_url
        ) VALUES (
            ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?
        )
    ');

    $stmt->execute([
        $data['crop_name'],
        $data['crop_type'],
        $data['crop_description'],
        $data['nitrogen_min']    ?? null,
        $data['nitrogen_max']    ?? null,
        $data['phosphorus_min']  ?? null,
        $data['phosphorus_max']  ?? null,
        $data['potassium_min']   ?? null,
        $data['potassium_max']   ?? null,
        $data['soil_ph_min']     ?? null,
        $data['soil_ph_max']     ?? null,
        $data['moisture_min']    ?? null,
        $data['moisture_max']    ?? null,
        $data['pic1_url'],
        $data['pic2_url']        ?? null,
        $data['pic3_url']        ?? null,
        $data['pic4_url']        ?? null,
        $data['pic5_url']        ?? null,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Crop created successfully',
        'id'      => $db->lastInsertId(),
    ]);
}

// PUT update existing crop
function updateCrop() {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing crop id']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        return;
    }

    $db = getConnection();
    $stmt = $db->prepare('
        UPDATE cropInfo SET
            crop_name      = ?,
            crop_type      = ?,
            crop_description = ?,
            nitrogen_min   = ?,
            nitrogen_max   = ?,
            phosphorus_min = ?,
            phosphorus_max = ?,
            potassium_min  = ?,
            potassium_max  = ?,
            soil_ph_min    = ?,
            soil_ph_max    = ?,
            moisture_min   = ?,
            moisture_max   = ?,
            pic1_url       = ?,
            pic2_url       = ?,
            pic3_url       = ?,
            pic4_url       = ?,
            pic5_url       = ?
        WHERE id = ?
    ');

    $stmt->execute([
        $data['crop_name'],
        $data['crop_type'],
        $data['crop_description'],
        $data['nitrogen_min']    ?? null,
        $data['nitrogen_max']    ?? null,
        $data['phosphorus_min']  ?? null,
        $data['phosphorus_max']  ?? null,
        $data['potassium_min']   ?? null,
        $data['potassium_max']   ?? null,
        $data['soil_ph_min']     ?? null,
        $data['soil_ph_max']     ?? null,
        $data['moisture_min']    ?? null,
        $data['moisture_max']    ?? null,
        $data['pic1_url'],
        $data['pic2_url']        ?? null,
        $data['pic3_url']        ?? null,
        $data['pic4_url']        ?? null,
        $data['pic5_url']        ?? null,
        $_GET['id'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Crop updated successfully']);
}

// DELETE crop
function deleteCrop() {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing crop id']);
        return;
    }

    $db = getConnection();
    $stmt = $db->prepare('DELETE FROM cropInfo WHERE id = ?');
    $stmt->execute([$_GET['id']]);

    echo json_encode(['success' => true, 'message' => 'Crop deleted successfully']);
}
