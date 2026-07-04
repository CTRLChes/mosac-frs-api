<?php
require_once 'cors.php';

echo json_encode([
    'success' => true,
    'message' => 'MoSAC-FRS API is running',
    'version' => '1.0.0',
    'endpoints' => [
        'GET  /crops'                      => 'Get all crops',
        'GET  /crops?id={id}'              => 'Get crop by id',
        'POST /crops'                      => 'Create crop',
        'PUT  /crops?id={id}'              => 'Update crop',
        'DELETE /crops?id={id}'            => 'Delete crop',
        'GET  /fertilizers'                => 'Get all fertilizers',
        'GET  /fertilizers?crop_type={t}'  => 'Get by crop type',
        'POST /fertilizers'                => 'Create fertilizer',
        'PUT  /fertilizers?id={id}'        => 'Update fertilizer',
        'DELETE /fertilizers?id={id}'      => 'Delete fertilizer',
        'GET  /evaluations'                => 'Get all evaluations',
        'GET  /evaluations?username={u}'   => 'Get by username',
        'POST /evaluations'                => 'Sync evaluation',
        'GET  /users'                      => 'Get all users',
        'GET  /users?username={u}'         => 'Get user by username',
    ],
]);
