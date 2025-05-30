<?php
require_once '../includes/db.php';

// 요청 메서드와 데이터 가져오기
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// GET이나 POST 데이터와 합치기
$data = array_merge(
    $input ? $input : array(),
    $_GET,
    $_POST
);

// 메서드별 처리
switch ($method) {
    case 'GET':
        // 회원 조회
        handle_get($data);
        break;
        
    case 'POST':
        // 회원 생성
        handle_post($data);
        break;
        
    default:
        error_response('Method not allowed', 405);
        break;
}

// 회원 조회 처리
function handle_get($data) {
    // 특정 회원 조회 (ID가 있는 경우)
    if (isset($data['id'])) {
        $id = intval($data['id']);
        $user = db_fetch_one("SELECT id, userid, name, email, profile_image, created_at FROM users WHERE id = {$id}");
        
        if (!$user) {
            error_response('User not found', 404);
        }
        
        success_response($user, 'User retrieved successfully');
    }
    else if (isset($data['userid'])){
        $userid = intval($data['userid']);
        $user = db_fetch_one("SELECT id, userid, name, email, profile_image, created_at FROM users WHERE userid = {$userid}");
        
        if (!$user) {
            error_response('User not found', 404);
        }
        
        success_response($user, 'User retrieved successfully');
    }
    else {
        // 전체 회원 목록 조회
        $users = db_fetch_all("SELECT id, userid, name, email, profile_image, created_at FROM users ORDER BY created_at DESC");
        success_response($users, 'Users retrieved successfully');
    }
}

// 회원 생성 처리
function handle_post($data) {
    // 필수 필드 검증
    if (empty($data['userid'])) {
        error_response('UID_REQUIRED');
    }
    
    if (empty($data['name'])) {
        error_response('NAME_REQUIRED');
    }
    
    if (empty($data['email'])) {
        error_response('EMAIL_REQUIRED');
    }
    
    if (empty($data['password'])) {
        error_response('PW_REQUIRED');
    }
    
    // 이메일 중복 검사
    $email = db_escape($data['email']);
    $existing_email = db_fetch_one("SELECT id FROM users WHERE email = '{$email}'");
    
    if ($existing_email) {
        error_response('EXISTING_EMAIL');
    }
    
    // 사용자 ID 중복 검사
    $userid = db_escape($data['userid']);
    $existing_userid = db_fetch_one("SELECT id FROM users WHERE userid = '{$userid}'");
    
    if ($existing_userid) {
        error_response('EXISTING_ID');
    }
    
    // 데이터 준비
    $name = db_escape($data['name']);
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $profile_image = isset($data['profile_image']) ? db_escape($data['profile_image']) : '/Image/default-avatar.png';
    
    // 회원 생성
    $query = "INSERT INTO users (userid, name, email, password, profile_image) 
              VALUES ('{$userid}', '{$name}', '{$email}', '{$password_hash}', '{$profile_image}')";
    db_query($query);
    
    // 생성된 회원 ID 가져오기
    $connection = db_connect();
    $new_id = mysql_insert_id($connection);
    
    // 생성된 회원 정보 반환 (비밀번호 제외)
    $new_user = db_fetch_one("SELECT id, userid, name, email, profile_image, created_at FROM users WHERE id = {$new_id}");
    
    // 201 Created 응답
    created_response($new_user, 'User created successfully');
}
?>