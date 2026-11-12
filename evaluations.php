<?php
require_once 'cors.php';
require_once 'database.php';

// Name of the online table that stores the recommended fertilizers of each
// evaluation. Expected columns: eval_id, fertilizer_id, rank_position.
// Change this if your table is named differently.
const EVAL_FERT_TABLE = 'evaluation_fertilizer';

// Only the top 3 recommended fertilizers are stored per evaluation.
const MAX_FERTILIZERS = 3;

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

// Cleans the fertilizer list sent by the app: top 3 only, integers only.
function cleanFertilizers($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $clean = [];
    foreach ($raw as $item) {
        if (!is_array($item) || !isset($item['fertilizer_id'], $item['rank_position'])) {
            continue;
        }
        $clean[] = [
            'fertilizer_id'  => (int) $item['fertilizer_id'],
            'rank_position'  => (int) $item['rank_position'],
        ];
    }

    usort($clean, function ($a, $b) {
        return $a['rank_position'] <=> $b['rank_position'];
    });

    return array_slice($clean, 0, MAX_FERTILIZERS);
}

function saveEvaluationFertilizers($db, $evalId, $fertilizers) {
    if (empty($fertilizers)) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO ' . EVAL_FERT_TABLE . ' (eval_id, fertilizer_id, rank_position) VALUES (?, ?, ?)'
    );
    foreach ($fertilizers as $f) {
        $stmt->execute([$evalId, $f['fertilizer_id'], $f['rank_position']]);
    }
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

    $fertilizers = cleanFertilizers($data['fertilizers'] ?? []);

    $db = getConnection();

    // Check if this evaluation already exists to prevent duplicates
    // using username + date + time as a unique identifier
    $check = $db->prepare('
        SELECT eval_id FROM evaluation
        WHERE username = ? AND date = ? AND time = ?
    ');
    $check->execute([$data['username'], $data['date'], $data['time']]);
    $existing = $check->fetch();

    if ($existing) {
        // Already synced. If it has no fertilizers yet, add them now.
        if (!empty($fertilizers)) {
            try {
                $count = $db->prepare(
                    'SELECT COUNT(*) FROM ' . EVAL_FERT_TABLE . ' WHERE eval_id = ?'
                );
                $count->execute([$existing['eval_id']]);
                if ((int) $count->fetchColumn() === 0) {
                    saveEvaluationFertilizers($db, $existing['eval_id'], $fertilizers);
                }
            } catch (Exception $e) {
                error_log('evaluations.php fertilizer backfill failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save fertilizers']);
                return;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Evaluation already synced',
            'duplicate' => true,
        ]);
        return;
    }

    try {
        // The evaluation and its fertilizers are saved together or not at all.
        $db->beginTransaction();

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

        $evalId = $db->lastInsertId();
        saveEvaluationFertilizers($db, $evalId, $fertilizers);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('evaluations.php save failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save evaluation']);
        return;
    }

    http_response_code(201);
    echo json_encode([
        'success'  => true,
        'message'  => 'Evaluation synced successfully',
        'eval_id'  => $evalId,
    ]);
}
