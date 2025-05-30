<?php
// includes/compat.php - PHP 5.6 호환성 함수들

// http_response_code 대체 함수
if (!function_exists('http_response_code')) {
    function http_response_code($code = null) {
        if ($code !== null) {
            switch($code) {
                case 200: header('HTTP/1.1 200 OK'); break;
                case 201: header('HTTP/1.1 201 Created'); break;
                case 400: header('HTTP/1.1 400 Bad Request'); break;
                case 401: header('HTTP/1.1 401 Unauthorized'); break;
                case 403: header('HTTP/1.1 403 Forbidden'); break;
                case 404: header('HTTP/1.1 404 Not Found'); break;
                case 405: header('HTTP/1.1 405 Method Not Allowed'); break;
                case 500: header('HTTP/1.1 500 Internal Server Error'); break;
                default: header('HTTP/1.1 ' . $code); break;
            }
        }
    }
}

// hash_equals 대체 함수 (보안상 중요)
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

// getallheaders 대체 함수 (Apache가 아닌 환경에서)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
?>