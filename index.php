<?php
require_once 'middleware/cors.php';

echo json_encode([
    'success' => true,
    'message' => 'MoSAC-FRS API is running',
    'version' => '1.0.0',
    'endpoints' => [
        'GET  /api/crops'            => 'Get all crops',
        'GET  /api/crops?id={id}'    => 'Get crop by id',
        'POST /api/crops'            => 'Create crop',
        'PUT  /api/crops?id={id}'    => 'Update crop',
        'DELETE /api/crops?id={id}'  => 'Delete crop',

        'GET  /api/fertilizers'                    => 'Get all fertilizers',
        'GET  /api/fertilizers?crop_type={type}'   => 'Get fertilizers by crop type',
        'POST /api/fertilizers'                    => 'Create fertilizer',
        'PUT  /api/fertilizers?id={id}'            => 'Update fertilizer',
        'DELETE /api/fertilizers?id={id}'          => 'Delete fertilizer',

        'GET  /api/evaluations'                    => 'Get all evaluations',
        'GET  /api/evaluations?username={username}' => 'Get evaluations by user',
        'POST /api/evaluations'                    => 'Sync evaluation from mobile',

        'GET  /api/users'                          => 'Get all users',
        'GET  /api/users?username={username}'      => 'Get user by username',
    ],
]);
