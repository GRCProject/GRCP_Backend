<?php
// api/team.php - 완전한 팀 관리 API
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// ✅ 인증 체크
$current_user = require_auth();


// 요청 메서드와 데이터 가져오기
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$data = array_merge(
    $input ? $input : array(),
    $_GET,
    $_POST
);

// URL에서 team_id 추출
$team_id = null;
if (isset($data['id'])) {
    $team_id = (int)$data['id'];
} elseif (isset($data['team_id'])) {
    $team_id = (int)$data['team_id'];
}

// 메서드별 처리
switch ($method) {
    case 'GET':
        if ($team_id) {
            handle_get_team_detail($current_user, $team_id);
        } else {
            handle_get_team_list($current_user);
        }
        break;
        
    case 'POST':
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'join':
                    handle_join_team($current_user, $data);
                    break;
                case 'leave':
                    handle_leave_team($current_user, $data);
                    break;
                default:
                    handle_create_team($current_user, $data);
                    break;
            }
        } else {
            handle_create_team($current_user, $data);
        }
        break;
        
    case 'PUT':
        handle_update_team($current_user, $team_id, $data);
        break;
        
    case 'DELETE':
        handle_delete_team($current_user, $team_id);
        break;
        
    default:
        error_response('Method not allowed', 405);
        break;
}

// 팀 목록 조회 (팀원 이름 포함)
function handle_get_team_list($current_user) {
    $user_id = (int)$current_user['id'];
    
    $sql = "SELECT DISTINCT
                t.id, 
                t.team_name, 
                t.admin_id, 
                t.created_at,
                u.name as admin_name,
                tm.position, 
                tm.role,
                tm.joined_at,
                (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
                (SELECT COUNT(*) FROM team_schedules WHERE team_id = t.id) as schedule_count
            FROM teams t
            LEFT JOIN users u ON t.admin_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = $user_id
            WHERE t.admin_id = $user_id OR tm.user_id = $user_id
            ORDER BY t.created_at DESC";
    
    $teams = db_fetch_all($sql);
    
    // 각 팀에 대해 팀원 정보 추가
    foreach ($teams as &$team) {
        $team_id = (int)$team['id'];
        
        // 팀원들 정보 조회 (이름, 역할, 직책 포함)
        $members_sql = "SELECT 
                           tm.position, 
                           tm.role, 
                           tm.joined_at,
                           u.id as user_id,
                           u.name, 
                           u.email,
                           u.profile_image
                       FROM team_members tm
                       JOIN users u ON tm.user_id = u.id
                       WHERE tm.team_id = $team_id
                       ORDER BY tm.joined_at ASC";
        
        $members = db_fetch_all($members_sql);
        
        // 팀원 이름들만 따로 배열로 만들기 (간단한 조회용)
        $member_names = array();
        foreach ($members as $member) {
            $member_names[] = $member['name'];
        }
        
        // 팀 정보에 추가
        $team['members'] = $members;           // 전체 팀원 정보
        $team['member_names'] = $member_names; // 팀원 이름만 배열
        $team['is_admin'] = ($team['admin_id'] == $current_user['id']);
    }
    
    success_response(array(
        'teams' => $teams,
        'user' => $current_user,
        'total_count' => count($teams)
    ), 'Team list retrieved successfully');
}

// 팀 상세 정보 조회 (멤버, 스케줄 포함)
function handle_get_team_detail($current_user, $team_id) {
    $user_id = (int)$current_user['id'];
    $team_id = (int)$team_id;
    
    // 팀 기본 정보 조회
    $sql = "SELECT DISTINCT
                t.id, 
                t.team_name, 
                t.admin_id, 
                t.created_at,
                u.name as admin_name,
                tm.position, 
                tm.role,
                tm.joined_at
            FROM teams t
            LEFT JOIN users u ON t.admin_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = $user_id
            WHERE t.id = $team_id AND (t.admin_id = $user_id OR tm.user_id = $user_id)";
    
    $team = db_fetch_one($sql);
    
    if (!$team) {
        error_response('Team not found or access denied', 404);
    }
    
    // 팀 멤버들 조회
    $members = get_team_members($team_id);
    
    // 팀 스케줄들 조회
    $schedules = get_team_schedules($team_id);
    
    // 통계 정보
    $stats = get_team_statistics($team_id);
    
    $team['members'] = $members;
    $team['schedules'] = $schedules;
    $team['statistics'] = $stats;
    $team['is_admin'] = ($team['admin_id'] == $current_user['id']);
    $team['member_count'] = count($members);
    $team['schedule_count'] = count($schedules);
    
    success_response(array(
        'team' => $team
    ), 'Team details retrieved successfully');
}

// 팀 멤버 정보 조회 (재사용 가능한 함수)
function get_team_members($team_id) {
    $team_id = (int)$team_id;
    
    $sql = "SELECT tm.id as member_id, tm.position, tm.role, tm.joined_at, 
                   u.id, u.name, u.email, u.profile_image
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = $team_id
            ORDER BY tm.joined_at ASC";
    
    return db_fetch_all($sql);
}

// 팀 스케줄 정보 조회 (재사용 가능한 함수)
function get_team_schedules($team_id) {
    $team_id = (int)$team_id;
    
    $sql = "SELECT id, schedule_name, start_date, end_date, status, created_at
            FROM team_schedules 
            WHERE team_id = $team_id
            ORDER BY start_date DESC";
    
    return db_fetch_all($sql);
}

// 팀 통계 정보 조회 (재사용 가능한 함수)
function get_team_statistics($team_id) {
    $team_id = (int)$team_id;
    
    // 스케줄 상태별 개수
    $schedule_stats = db_fetch_all("SELECT status, COUNT(*) as count FROM team_schedules WHERE team_id = $team_id GROUP BY status");
    
    // 개인 스케줄 상태별 개수
    $personal_stats = db_fetch_all("SELECT ps.detail_status, COUNT(*) as count FROM personal_schedule ps JOIN team_schedules ts ON ps.team_schedules_id = ts.id WHERE ts.team_id = $team_id GROUP BY ps.detail_status");
    
    // 역할별 멤버 개수
    $role_stats = db_fetch_all("SELECT role, COUNT(*) as count FROM team_members WHERE team_id = $team_id AND role != '' GROUP BY role");
    
    return array(
        'schedule_by_status' => $schedule_stats,
        'personal_schedule_by_status' => $personal_stats,
        'members_by_role' => $role_stats
    );
}

// 팀 생성
function handle_create_team($current_user, $data) {
    // 🔍 디버깅: 현재 사용자 정보 확인
    error_log("Creating team - Current user: " . print_r($current_user, true));

    // 입력 검증
    if (empty($data['team_name'])) {
        error_response('Team name is required');
    }
    
    $team_name = db_escape(trim($data['team_name']));
    $admin_id = (int)$current_user['id'];
    
    // 팀명 중복 체크
    $existing_team = db_fetch_one("SELECT id FROM teams WHERE team_name = '$team_name' AND admin_id = $admin_id");
    if ($existing_team) {
        error_response('You already have a team with this name', 409);
    }
    
    // 팀 생성
    $query = "INSERT INTO teams (team_name, admin_id, created_at) VALUES ('$team_name', $admin_id, NOW())";
    
    if (db_query($query)) {
        // 생성된 팀 ID 가져오기
        $result = db_fetch_one("SELECT LAST_INSERT_ID() as id");
        $new_team_id = (int)$result['id'];
        
        // 관리자를 팀 멤버로 자동 추가
        $member_query = "INSERT INTO team_members (team_id, user_id, position, role, joined_at) VALUES ($new_team_id, $admin_id, '팀장', '관리자', NOW())";
        db_query($member_query);
        
        // 생성된 팀 정보 반환
        $team = db_fetch_one("SELECT t.*, u.name as admin_name FROM teams t LEFT JOIN users u ON t.admin_id = u.id WHERE t.id = $new_team_id");
        
        success_response(array(
            'team' => $team,
            'message' => 'Team created successfully'
        ), 'Team created successfully', 201);
    } else {
        error_response('Failed to create team', 500);
    }
}

// 팀 정보 수정 (팀 관리자만 가능)
function handle_update_team($current_user, $team_id, $data) {
    if (!$team_id) {
        error_response('Team ID is required');
    }
    
    if (empty($data['team_name'])) {
        error_response('Team name is required');
    }
    
    $team_name = db_escape(trim($data['team_name']));
    $user_id = (int)$current_user['id'];
    $team_id = (int)$team_id;
    
    // 팀 관리자 권한 확인
    $team = db_fetch_one("SELECT admin_id FROM teams WHERE id = $team_id");
    if (!$team || $team['admin_id'] != $user_id) {
        error_response('Permission denied. Only team admin can update team.', 403);
    }
    
    // 팀명 중복 체크 (다른 팀과 중복 방지)
    $existing_team = db_fetch_one("SELECT id FROM teams WHERE team_name = '$team_name' AND admin_id = $user_id AND id != $team_id");
    if ($existing_team) {
        error_response('You already have another team with this name', 409);
    }
    
    // 팀 정보 업데이트
    $query = "UPDATE teams SET team_name = '$team_name' WHERE id = $team_id";
    
    if (db_query($query)) {
        // 업데이트된 팀 정보 반환
        $updated_team = db_fetch_one("SELECT t.*, u.name as admin_name FROM teams t LEFT JOIN users u ON t.admin_id = u.id WHERE t.id = $team_id");
        
        success_response(array(
            'team' => $updated_team
        ), 'Team updated successfully');
    } else {
        error_response('Failed to update team', 500);
    }
}

// 팀 삭제 (팀 관리자만 가능)
function handle_delete_team($current_user, $team_id) {
    if (!$team_id) {
        error_response('Team ID is required');
    }
    
    $user_id = (int)$current_user['id'];
    $team_id = (int)$team_id;
    
    // 팀 관리자 권한 확인
    $team = db_fetch_one("SELECT admin_id, team_name FROM teams WHERE id = $team_id");
    if (!$team || $team['admin_id'] != $user_id) {
        error_response('Permission denied. Only team admin can delete team.', 403);
    }
    
    // 관련 데이터 삭제 (외래키 제약 때문에 순서 중요)
    try {
        // 1. 개인 스케줄 삭제
        db_query("DELETE ps FROM personal_schedule ps JOIN team_schedules ts ON ps.team_schedules_id = ts.id WHERE ts.team_id = $team_id");
        
        // 2. 팀 스케줄 삭제
        db_query("DELETE FROM team_schedules WHERE team_id = $team_id");
        
        // 3. 팀 멤버 삭제
        db_query("DELETE FROM team_members WHERE team_id = $team_id");
        
        // 4. 팀 삭제
        db_query("DELETE FROM teams WHERE id = $team_id");
        
        success_response(array(
            'team_name' => $team['team_name']
        ), 'Team and all related data deleted successfully');
        
    } catch (Exception $e) {
        error_response('Failed to delete team: ' . $e->getMessage(), 500);
    }
}

// 팀 가입 (팀 관리자가 다른 사용자를 초대)
function handle_join_team($current_user, $data) {
    if (empty($data['team_id']) || empty($data['user_email'])) {
        error_response('Team ID and user email are required');
    }
    
    $team_id = (int)$data['team_id'];
    $user_email = db_escape($data['user_email']);
    $position = db_escape(isset($data['position']) ? $data['position'] : '팀원');
    $role = db_escape(isset($data['role']) ? $data['role'] : '');
    $admin_id = (int)$current_user['id'];
    
    // 팀 관리자 권한 확인
    $team = db_fetch_one("SELECT admin_id FROM teams WHERE id = $team_id");
    if (!$team || $team['admin_id'] != $admin_id) {
        error_response('Permission denied. Only team admin can add members.', 403);
    }
    
    // 초대할 사용자 확인
    $user_to_add = db_fetch_one("SELECT id, name FROM users WHERE email = '$user_email'");
    if (!$user_to_add) {
        error_response('User not found with this email', 404);
    }
    
    $user_id_to_add = (int)$user_to_add['id'];
    
    // 이미 팀 멤버인지 확인
    $existing_member = db_fetch_one("SELECT id FROM team_members WHERE team_id = $team_id AND user_id = $user_id_to_add");
    if ($existing_member) {
        error_response('User is already a member of this team', 409);
    }
    
    // 팀 멤버 추가
    $query = "INSERT INTO team_members (team_id, user_id, position, role, joined_at) VALUES ($team_id, $user_id_to_add, '$position', '$role', NOW())";
    
    if (db_query($query)) {
        success_response(array(
            'user' => $user_to_add,
            'position' => $position,
            'role' => $role
        ), 'Member added to team successfully');
    } else {
        error_response('Failed to add member to team', 500);
    }
}

// 팀 탈퇴
function handle_leave_team($current_user, $data) {
    if (empty($data['team_id'])) {
        error_response('Team ID is required');
    }
    
    $team_id = (int)$data['team_id'];
    $user_id = (int)$current_user['id'];
    
    // 팀 관리자는 팀을 떠날 수 없음
    $team = db_fetch_one("SELECT admin_id FROM teams WHERE id = $team_id");
    if ($team && $team['admin_id'] == $user_id) {
        error_response('Team admin cannot leave the team. Transfer admin rights or delete the team instead.', 403);
    }
    
    // 팀 멤버인지 확인
    $member = db_fetch_one("SELECT id FROM team_members WHERE team_id = $team_id AND user_id = $user_id");
    if (!$member) {
        error_response('You are not a member of this team', 404);
    }
    
    // 관련 개인 스케줄 삭제 (변경된 스키마에 맞춰)
    $member_id = (int)$member['id'];
    db_query("DELETE FROM personal_schedule WHERE team_member_id = $member_id");
    
    // 팀에서 제거
    $query = "DELETE FROM team_members WHERE team_id = $team_id AND user_id = $user_id";
    
    if (db_query($query)) {
        success_response(array(), 'Successfully left the team');
    } else {
        error_response('Failed to leave team', 500);
    }
}
?>