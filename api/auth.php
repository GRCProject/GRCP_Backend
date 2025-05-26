<?php
require_once 'includes/db.php';

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
    case 'POST':
        // 로그인 처리
        handle_login($data);
        break;
        
    default:
        error_response('Method not allowed', 405);
        break;
}

// 로그인 처리
function handle_login($data) {
    // 필수 필드 검증
    if (empty($data['login']) && empty($data['email']) && empty($data['userid'])) {
        error_response('Email or User ID is required');
    }
    
    if (empty($data['password'])) {
        error_response('Password is required');
    }
    
    $password = $data['password'];
    $user = null;
    
    // 로그인 방식 결정 (email, userid, 또는 login 필드로 통합)
    if (!empty($data['login'])) {
        // login 필드가 있으면 email 또는 userid로 검색
        $login = db_escape($data['login']);
        $user = db_fetch_one("SELECT id, userid, name, email, password, profile_image FROM users 
                             WHERE email = '{$login}' OR userid = '{$login}'");
    } elseif (!empty($data['email'])) {
        // email로 로그인
        $email = db_escape($data['email']);
        $user = db_fetch_one("SELECT id, userid, name, email, password, profile_image FROM users 
                             WHERE email = '{$email}'");
    } elseif (!empty($data['userid'])) {
        // userid로 로그인
        $userid = db_escape($data['userid']);
        $user = db_fetch_one("SELECT id, userid, name, email, password, profile_image FROM users 
                             WHERE userid = '{$userid}'");
    }
    
    if (!$user) {
        error_response('Invalid credentials', 401);
    }
    
    // 비밀번호 검증
    if (!password_verify($password, $user['password'])) {
        error_response('Invalid credentials', 401);
    }
    
    // 간단한 토큰 생성
    $token = base64_encode($user['id'] . ':' . time() . ':' . $user['userid']);
    
    // 응답 (비밀번호 제외)
    success_response(array(
        'user' => array(
            'id' => $user['id'],
            'userid' => $user['userid'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile_image' => $user['profile_image']
        ),
        'token' => $token
    ), 'Login successful');
}
?>