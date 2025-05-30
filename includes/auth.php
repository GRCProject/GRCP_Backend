<?php
// includes/auth.php - PHP 5.6 완전 호환 버전

require_once 'config.php';

// hash_equals 함수가 없으면 대체 함수 생성
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string)) {
            return false;
        }
        if (!is_string($user_string)) {
            return false;
        }
        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < strlen($known_string); $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        
        return $result === 0;
    }
}

// 중복 정의 방지
if (!function_exists('generate_token')) {

function generate_token($user_id, $userid) {
    $secret_key = JWT_SECRET_KEY;
    $expire_time = JWT_EXPIRE_TIME;
    
    $timestamp = time();
    $exp_time = $timestamp + $expire_time;
    
    $payload = array(
        'user_id' => $user_id,
        'userid' => $userid,
        'exp' => $exp_time,
        'iat' => $timestamp,
        'project' => PROJECT_NAME
    );
    
    $payload_encoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $payload_encoded, $secret_key);
    
    return $payload_encoded . '.' . $signature;
}

} // generate_token 함수 존재 체크 끝

if (!function_exists('verify_token')) {

function verify_token($token) {
    error_log("=== VERIFY_TOKEN DEBUG ===");
    error_log("Token: " . ($token ? substr($token, 0, 30) . "..." : "NULL"));
    
    if (empty($token)) {
        error_log("Token is empty");
        return false;
    }
    
    $secret_key = JWT_SECRET_KEY;
    error_log("Secret key defined: " . (defined('JWT_SECRET_KEY') ? 'YES' : 'NO'));
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        error_log("Invalid token format - parts: " . count($parts));
        return false;
    }
    
    $payload_encoded = $parts[0];
    $signature = $parts[1];
    
    // 서명 검증
    $expected_signature = hash_hmac('sha256', $payload_encoded, $secret_key);
    error_log("Expected signature: " . substr($expected_signature, 0, 20) . "...");
    error_log("Received signature: " . substr($signature, 0, 20) . "...");
    
    if (!hash_equals($expected_signature, $signature)) {
        error_log("Signature verification failed");
        return false;
    }
    
    // 페이로드 디코딩
    $payload_json = base64_decode($payload_encoded);
    error_log("Decoded payload JSON: " . $payload_json);
    
    $payload = json_decode($payload_json, true);
    if (!$payload) {
        error_log("Failed to decode payload");
        return false;
    }
    
    error_log("Parsed payload: " . print_r($payload, true));
    
    // 만료 시간 검증
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        error_log("Token expired. Exp: " . $payload['exp'] . ", Now: " . time());
        return false;
    }
    
    // 프로젝트 검증
    if (!isset($payload['project']) || $payload['project'] !== PROJECT_NAME) {
        error_log("Project validation failed. Expected: " . PROJECT_NAME . ", Got: " . (isset($payload['project']) ? $payload['project'] : 'NULL'));
        return false;
    }
    
    error_log("Token verification successful");
    return $payload;
}

} // verify_token 함수 존재 체크 끝

if (!function_exists('get_authenticated_user')) {

function get_authenticated_user() {
    error_log("=== GET_AUTHENTICATED_USER DEBUG ===");
    
    $token = get_token_from_request();
    error_log("Token from request: " . ($token ? "Found (" . strlen($token) . " chars)" : "NULL"));
    
    $payload = verify_token($token);
    error_log("Payload from verify_token: " . ($payload ? "Valid" : "Invalid"));
    
    if (!$payload) {
        error_log("No valid payload, returning null");
        return null;
    }
    
    if (!isset($payload['user_id'])) {
        error_log("No user_id in payload");
        return null;
    }
    
    $user_id = db_escape($payload['user_id']);
    error_log("User ID from payload: " . $user_id);
    
    $query = "SELECT id, userid, name, email, profile_image FROM users WHERE id = '$user_id'";
    error_log("DB Query: " . $query);
    
    $user = db_fetch_one($query);
    error_log("User from DB: " . ($user ? print_r($user, true) : "NULL"));
    
    return $user;
}

} // get_authenticated_user 함수 존재 체크 끝

if (!function_exists('get_token_from_request')) {

function get_token_from_request() {
    error_log("=== GET_TOKEN_FROM_REQUEST DEBUG ===");
    
    // Authorization 헤더에서 토큰 추출
    $headers = getallheaders();
    error_log("All headers: " . print_r($headers, true));
    
    if (isset($headers['Authorization'])) {
        error_log("Authorization header found: " . $headers['Authorization']);
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            error_log("Bearer token extracted: " . substr($matches[1], 0, 50) . "...");
            return $matches[1];
        }
    }
    
    // 대소문자 구분 없이 헤더 확인
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            error_log("Authorization header found (lowercase): " . $value);
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                error_log("Bearer token extracted (lowercase): " . substr($matches[1], 0, 50) . "...");
                return $matches[1];
            }
        }
    }
    
    // POST/GET 데이터에서 토큰 추출
    if (isset($_POST['token'])) {
        error_log("Token found in POST");
        return $_POST['token'];
    }
    
    if (isset($_GET['token'])) {
        error_log("Token found in GET");
        return $_GET['token'];
    }
    
    // JSON 입력에서 토큰 추출
    $input_raw = file_get_contents('php://input');
    error_log("Raw input: " . $input_raw);
    
    $input = json_decode($input_raw, true);
    if (isset($input['token'])) {
        error_log("Token found in JSON input");
        return $input['token'];
    }
    
    error_log("No token found anywhere");
    return null;
}

} // get_token_from_request 함수 존재 체크 끝

if (!function_exists('require_auth')) {

function require_auth() {
    error_log("=== REQUIRE_AUTH DEBUG ===");
    
    $user = get_authenticated_user();
    error_log("Final user from get_authenticated_user: " . ($user ? print_r($user, true) : "NULL"));
    
    if (!$user) {
        error_log("Authentication failed - returning 401");
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => 'Authentication required',
            'error_code' => 'AUTH_REQUIRED'
        ));
        exit;
    }
    
    error_log("Authentication successful - returning user");
    return $user;
}

} // require_auth 함수 존재 체크 끝

if (!function_exists('handle_logout')) {

function handle_logout() {
    success_response(array(), 'Logout successful');
}

} // handle_logout 함수 존재 체크 끝
?>