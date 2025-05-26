<?php
// 필요한 파일 포함
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 간단한 라우팅
$request_uri = $_SERVER['REQUEST_URI'];
$path = strtok($request_uri, '?');

// 프로젝트 경로 제거
$base_path = '/grcProject';
if (strpos($path, $base_path) === 0) {
    $path = substr($path, strlen($base_path));
}

// 빈 경로 처리
if (empty($path) || $path === '/') {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 200 OK');
    echo json_encode(array(
        'success' => true,
        'message' => 'GRC Project API appropriately working.',
        'available_endpoints' => array(
            'GET /api/users' => 'search Member List',
            'GET /api/users?id=1' => 'search specific member',
            'GET /api/users?userid=userid' => 'search specific member',
            'POST /api/users' => 'create member'
        )
    ));
    exit;
}

// 라우팅
if ($path === '/api/users' || strpos($path, '/api/users') === 0) {
    require_once 'api/users.php';
} else {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 404 Not Found');
    echo json_encode(array(
        'success' => false,
        'error' => 'endpoint doesn\'t exist.',
        'requested_path' => $path,
        'available_endpoints' => array('/api/users')
    ));
}
?>