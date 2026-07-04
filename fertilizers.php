<?php
require_once 'cors.php';
require_once 'database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getFertilizers();
        break;
    case 'POST':
        createFertilizer();
        break;
    case 'PUT':
        updateFertilizer();
        break;
    case 'DELETE':
        deleteFertilizer();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// GET all fertilizers or filter by crop_type
function getFertilizers() {
    $db = getConnection();

    if (isset($_GET['id'])) {
        $stmt = $db->prepare('SELECT * FROM fertilizer WHERE fert_id = ?');
        $stmt->execute([$_GET['id']]);
        $fert = $stmt->fetch();

        if (!$fert) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Fertilizer not found']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $fert]);
    } elseif (isset($_GET['crop_type'])) {
        $stmt = $db->prepare('SELECT * FROM fertilizer WHERE crop_type = ? ORDER BY fertilizer_name ASC');
        $stmt->execute([$_GET['crop_type']]);
        $ferts = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $ferts]);
    } else {
        $stmt = $db->query('SELECT * FROM fertilizer ORDER BY fertilizer_name ASC');
        $ferts = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $ferts]);
    }
}

// POST create fertilizer
function createFertilizer() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        return;
    }

    $required = [
        'fertilizer_name', 'fertilizer_type', 'crop_type',
        'nitrogen_content', 'phosphorus_content', 'potassium_content',
        'application_method', 'application_time', 'description', 'fert_pic1_url'
    ];

    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    $db = getConnection();
    $stmt = $db->prepare('
        INSERT INTO fertilizer (
            fertilizer_name, fertilizer_type, crop_type,
            nitrogen_content, phosphorus_content, potassium_content,
            application_method, application_time, description,
            fert_pic1_url, fert_pic2_url, fert_pic3_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $data['fertilizer_name'],
        $data['fertilizer_type'],
        $data['crop_type'],
        $data['nitrogen_content'],
        $data['phosphorus_content'],
        $data['potassium_content'],
        $data['application_method'],
        $data['application_time'],
        $data['description'],
        $data['fert_pic1_url'],
        $data['fert_pic2_url'] ?? null,
        $data['fert_pic3_url'] ?? null,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Fertilizer created successfully',
        'id'      => $db->lastInsertId(),
    ]);
}

// PUT update fertilizer
function updateFertilizer() {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing fertilizer id']);
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
        UPDATE fertilizer SET
            fertilizer_name    = ?,
            fertilizer_type    = ?,
            crop_type          = ?,
            nitrogen_content   = ?,
            phosphorus_content = ?,
            potassium_content  = ?,
            application_method = ?,
            application_time   = ?,
            description        = ?,
            fert_pic1_url      = ?,
            fert_pic2_url      = ?,
            fert_pic3_url      = ?
        WHERE fert_id = ?
    ');

    $stmt->execute([
        $data['fertilizer_name'],
        $data['fertilizer_type'],
        $data['crop_type'],
        $data['nitrogen_content'],
        $data['phosphorus_content'],
        $data['potassium_content'],
        $data['application_method'],
        $data['application_time'],
        $data['description'],
        $data['fert_pic1_url'],
        $data['fert_pic2_url'] ?? null,
        $data['fert_pic3_url'] ?? null,
        $_GET['id'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Fertilizer updated successfully']);
}

// DELETE fertilizer
function deleteFertilizer() {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing fertilizer id']);
        return;
    }

    $db = getConnection();
    $stmt = $db->prepare('DELETE FROM fertilizer WHERE fert_id = ?');
    $stmt->execute([$_GET['id']]);

    echo json_encode(['success' => true, 'message' => 'Fertilizer deleted successfully']);
}
