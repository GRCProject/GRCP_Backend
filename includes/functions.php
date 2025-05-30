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

// ✅ PHP 5.6 호환 - 한글 깨짐 해결된 응답 함수들
function success_response($data, $message = 'Success') {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 200 OK');
    
    $response = array(
        'success' => true,
        'message' => $message,
        'data' => $data
    );
    
    // ✅ PHP 5.6 호환: 한글 유니코드 처리
    $json = json_encode($response);
    echo unicode_decode($json);
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
    
    // ✅ PHP 5.6 호환: 한글 유니코드 처리
    $json = json_encode($response);
    echo unicode_decode($json);
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
    
    // ✅ PHP 5.6 호환: 한글 유니코드 처리
    $json = json_encode($response);
    echo unicode_decode($json);
    exit;
}

// ✅ PHP 5.6용 유니코드 디코딩 함수
function unicode_decode($json) {
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', 'unicode_decode_callback', $json);
}

// 유니코드 디코딩 콜백 함수 (PHP 5.6 호환)
function unicode_decode_callback($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

// ✅ 추가 유틸리티 함수들
function validate_required($data, $required_fields) {
    $missing_fields = array();
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        error_response('Missing required fields: ' . implode(', ', $missing_fields), 400);
    }
    
    return true;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// 페이지네이션 응답 함수
function paginated_response($data, $page, $per_page, $total, $message = 'Success') {
    header('Content-Type: application/json; charset=utf-8');
    header('HTTP/1.1 200 OK');
    
    $total_pages = ceil($total / $per_page);
    
    $response = array(
        'success' => true,
        'message' => $message,
        'data' => $data,
        'pagination' => array(
            'current_page' => (int)$page,
            'per_page' => (int)$per_page,
            'total' => (int)$total,
            'total_pages' => (int)$total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        )
    );
    
    // ✅ PHP 5.6 호환: 한글 유니코드 처리
    $json = json_encode($response);
    echo unicode_decode($json);
    exit;
}
?>