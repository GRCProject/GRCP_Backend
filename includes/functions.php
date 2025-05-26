<?php
// 대체 함수 개발을 위한 php 파일

// password_hash 함수가 없는 경우를 위한 대체 함수
if (!function_exists('password_hash')) {
    function password_hash($password, $algo) {
        // PASSWORD_DEFAULT는 보통 1 (BCRYPT)
        if ($algo == PASSWORD_DEFAULT || $algo == 1) {
            // 간단한 salt 생성
            $salt = '$2y$10$' . substr(md5(uniqid()), 0, 22);
            return crypt($password, $salt);
        }
        return false;
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        return crypt($password, $hash) === $hash;
    }
}

if (!defined('PASSWORD_DEFAULT')) {
    define('PASSWORD_DEFAULT', 1);
}

// 기존 응답 함수들...
function success_response($data, $message = 'Success') {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 200 OK');
    
    $response = array(
        'success' => true,
        'message' => $message,
        'data' => $data
    );
    
    echo json_encode($response);
    exit;
}

function error_response($message, $code = 400) {
    header('Content-Type: application/json; charset=utf-8');
    
    switch ($code) {
        case 400:
            header('HTTP/1.1 400 Bad Request');
            break;
        case 401:
            header('HTTP/1.1 401 Unauthorized');
            break;
        case 403:
            header('HTTP/1.1 403 Forbidden');
            break;
        case 404:
            header('HTTP/1.1 404 Not Found');
            break;
        case 405:
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 500:
            header('HTTP/1.1 500 Internal Server Error');
            break;
        default:
            header('HTTP/1.1 400 Bad Request');
            break;
    }
    
    $response = array(
        'success' => false,
        'error' => $message
    );
    
    echo json_encode($response);
    exit;
}

function created_response($data, $message = 'Created') {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 201 Created');
    
    $response = array(
        'success' => true,
        'message' => $message,
        'data' => $data
    );
    
    echo json_encode($response);
    exit;
}
?>