<?php
require_once 'cors.php';
require_once 'database.php';

// Handle verify login before method routing
if (isset($_GET['action']) && $_GET['action'] === 'verify') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['username']) || empty($data['pin_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing username or pin_hash']);
        exit;
    }
    $db = getConnection();
    $stmt = $db->prepare('
        SELECT id, full_name, username, pin_hash,
               security_question, security_answer, role
        FROM users
        WHERE username = ? AND pin_hash = ?
    ');
    $stmt->execute([$data['username'], $data['pin_hash']]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or PIN']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $user]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':    getUsers();    break;
    case 'POST':   createUser();  break;
    case 'PUT':    updateUser();  break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getUsers() {
    $db = getConnection();

    if (isset($_GET['username'])) {
        $stmt = $db->prepare('
            SELECT id, full_name, username, pin_hash,
                   security_question, security_answer, role
            FROM users WHERE username = ?
        ');
        $stmt->execute([$_GET['username']]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $user]);
        return;
    }

    $stmt = $db->query('SELECT id, full_name, username, role FROM users ORDER BY id DESC');
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['username']) || empty($data['pin_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing username or pin_hash']);
            return;
        }
        $stmt = $db->prepare('
            SELECT id, full_name, username, pin_hash,
                   security_question, security_answer, role
            FROM users
            WHERE username = ? AND pin_hash = ?
        ');
        $stmt->execute([$data['username'], $data['pin_hash']]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid username or PIN']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $user]);
        return;
    }

    // Get single user by username (includes security_question for profile restore)
    if (isset($_GET['username'])) {
        $stmt = $db->prepare('
            SELECT id, full_name, username, pin_hash,
                   security_question, security_answer, role
            FROM users WHERE username = ?
        ');
        $stmt->execute([$_GET['username']]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $user]);
        return;
    }

    // Get all users (admin use)
    $stmt = $db->query('SELECT id, full_name, username, role FROM users ORDER BY id DESC');
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function createUser() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid JSON']); return; }

    $required = ['full_name', 'username', 'pin_hash', 'security_question', 'security_answer'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing: $field"]);
            return;
        }
    }

    $db = getConnection();
    $check = $db->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$data['username']]);
    if ($check->fetch()) {
        echo json_encode(['success' => true, 'message' => 'User already exists', 'duplicate' => true]);
        return;
    }

    $stmt = $db->prepare('INSERT INTO users (full_name, username, pin_hash, security_question, security_answer, role) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['full_name'],
        $data['username'],
        $data['pin_hash'],
        $data['security_question'],
        $data['security_answer'],
        $data['role'] ?? 'Farmer',
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'User registered', 'id' => $db->lastInsertId()]);
}

function updateUser() {
    if (!isset($_GET['username'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing username in query']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid JSON']); return; }

    $db = getConnection();
    $currentUsername = $_GET['username'];

    if (!empty($data['new_username'])) {
        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$data['new_username']]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            return;
        }
        $stmt = $db->prepare('UPDATE users SET username = ? WHERE username = ?');
        $stmt->execute([$data['new_username'], $currentUsername]);
        echo json_encode(['success' => true, 'message' => 'Username updated']);
        return;
    }

    if (!empty($data['pin_hash'])) {
        $stmt = $db->prepare('UPDATE users SET pin_hash = ? WHERE username = ?');
        $stmt->execute([$data['pin_hash'], $currentUsername]);
        echo json_encode(['success' => true, 'message' => 'PIN updated']);
        return;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nothing to update']);
}
