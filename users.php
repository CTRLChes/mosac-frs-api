<?php
require_once 'cors.php';
require_once 'database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUsers();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// GET all users (admin only — never expose pin_hash or security_answer)
function getUsers() {
    $db = getConnection();

    if (isset($_GET['username'])) {
        $stmt = $db->prepare('
            SELECT id, full_name, username, role
            FROM users
            WHERE username = ?
        ');
        $stmt->execute([$_GET['username']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        $stmt = $db->query('
            SELECT id, full_name, username, role
            FROM users
            ORDER BY id DESC
        ');
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $users]);
    }
}
