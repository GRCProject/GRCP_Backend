<?php
// 데이터베이스 설정 (XAMPP 기본값)
define('DB_HOST', 'localhost');        // 호스트 (거의 항상 localhost)
define('DB_USER', 'root');             // 사용자명 (XAMPP 기본값: root)
define('DB_PASS', '1234');             // 비밀번호 (XAMPP 기본값: 빈 문자열)
define('DB_NAME', 'grc_db');          // 데이터베이스 이름 (직접 생성해야 함)

// JWT Secret Key 설정 (토큰 암호화용 비밀키)
define('JWT_SECRET_KEY', 'grcProject2024!@#$SecretKey&*()');//실제론 더 복잡하게');

// 토큰 만료 시간 설정 (초 단위)
define('JWT_EXPIRE_TIME', 24 * 60 * 60); // 24시간

// 문자 인코딩 설정
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 오류 표시 설정 (개발용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS 헤더 설정 (더 안전하게 개선)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Authorization 헤더 추가
header('Content-Type: application/json; charset=utf-8'); // JSON 응답용

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 프로젝트 설정
define('PROJECT_NAME', 'GRC Project');
define('PROJECT_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/grcProject/'); // 프로젝트 기본 URL
?>