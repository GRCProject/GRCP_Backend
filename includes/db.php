<?php
require_once 'config.php';
require_once 'functions.php';  // functions.php 포함

// 데이터베이스 연결
function db_connect() {
    static $connection;
    
    if (!isset($connection)) {
        $connection = mysql_connect(DB_HOST, DB_USER, DB_PASS);
        
        if (!$connection) {
            die('데이터베이스 연결 실패: ' . mysql_error());
        }
        
        mysql_select_db(DB_NAME, $connection);
        // 중요: 문자셋을 UTF-8로 설정
        mysql_query("SET NAMES 'utf8'", $connection);
        mysql_query("SET CHARACTER SET utf8", $connection);
        mysql_query("SET character_set_connection=utf8", $connection);
    }
    
    return $connection;
}

// 쿼리 실행
function db_query($query) {
    $connection = db_connect();
    $result = mysql_query($query, $connection);
    
    if (!$result) {
        die('쿼리 실행 오류: ' . mysql_error() . ' SQL: ' . $query);
    }
    
    return $result;
}

// 여러 행 가져오기
function db_fetch_all($query) {
    $result = db_query($query);
    $rows = array();
    
    while ($row = mysql_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    return $rows;
}

// 단일 행 가져오기
function db_fetch_one($query) {
    $result = db_query($query);
    return mysql_fetch_assoc($result);
}

// SQL 인젝션 방지
function db_escape($value) {
    $connection = db_connect();
    return mysql_real_escape_string($value, $connection);
}
?>