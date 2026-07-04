<?php
require_once 'cors.php';
require_once 'database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getEvaluations();
        break;
    case 'POST':
        createEvaluation();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// GET all evaluations or filter by username
function getEvaluations() {
    $db = getConnection();

    if (isset($_GET['username'])) {
        $stmt = $db->prepare('
            SELECT * FROM evaluation
            WHERE username = ?
            ORDER BY eval_id DESC
        ');
        $stmt->execute([$_GET['username']]);
    } else {
        $stmt = $db->query('SELECT * FROM evaluation ORDER BY eval_id DESC');
    }

    $evaluations = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $evaluations]);
}

// POST create evaluation (called by mobile app when syncing)
function createEvaluation() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        return;
    }

    $required = [
        'username', 'nitrogen', 'phosphorus', 'potassium',
        'soil_ph', 'moisture', 'latitude', 'longitude',
        'date', 'time'
    ];

    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    $db = getConnection();

    // Check if this evaluation already exists to prevent duplicates
    // using username + date + time as a unique identifier
    $check = $db->prepare('
        SELECT eval_id FROM evaluation
        WHERE username = ? AND date = ? AND time = ?
    ');
    $check->execute([$data['username'], $data['date'], $data['time']]);

    if ($check->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Evaluation already synced',
            'duplicate' => true,
        ]);
        return;
    }

    $stmt = $db->prepare('
        INSERT INTO evaluation (
            username, nitrogen, phosphorus, potassium,
            soil_ph, moisture, latitude, longitude,
            location, date, time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $data['username'],
        $data['nitrogen'],
        $data['phosphorus'],
        $data['potassium'],
        $data['soil_ph'],
        $data['moisture'],
        $data['latitude'],
        $data['longitude'],
        $data['location'] ?? null,
        $data['date'],
        $data['time'],
    ]);

    http_response_code(201);
    echo json_encode([
        'success'  => true,
        'message'  => 'Evaluation synced successfully',
        'eval_id'  => $db->lastInsertId(),
    ]);
}
