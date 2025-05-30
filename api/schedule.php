<?php
// api/schedule.php - 스케줄 관리 API
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

// URL에서 ID 추출
$schedule_id = null;
$team_id = null;

if (isset($data['id'])) {
    $schedule_id = (int)$data['id'];
}
if (isset($data['schedule_id'])) {
    $schedule_id = (int)$data['schedule_id'];
}
if (isset($data['team_id'])) {
    $team_id = (int)$data['team_id'];
}

// 메서드별 처리
switch ($method) {
    case 'GET':
        if ($schedule_id) {
            // 특정 스케줄 상세 조회
            handle_get_schedule_detail($current_user, $schedule_id);
        } elseif ($team_id) {
            // 특정 팀의 스케줄 목록 조회
            handle_get_team_schedules($current_user, $team_id);
        } else {
            // 사용자의 모든 스케줄 조회
            handle_get_my_schedules($current_user, $data);
        }
        break;
        
    case 'POST':
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'create_personal':
                    // 개인 스케줄(세부사항) 생성
                    handle_create_personal_schedule($current_user, $data);
                    break;
                case 'update_status':
                    // 스케줄 상태 업데이트
                    handle_update_schedule_status($current_user, $data);
                    break;
                default:
                    // 기본: 팀 스케줄 생성
                    handle_create_team_schedule($current_user, $data);
                    break;
            }
        } else {
            // 팀 스케줄 생성
            handle_create_team_schedule($current_user, $data);
        }
        break;
        
    case 'PUT':
        if (isset($data['type']) && $data['type'] === 'personal') {
            // 개인 스케줄 수정
            handle_update_personal_schedule($current_user, $data);
        } else {
            // 팀 스케줄 수정
            handle_update_team_schedule($current_user, $schedule_id, $data);
        }
        break;
        
    case 'DELETE':
        if (isset($data['type']) && $data['type'] === 'personal') {
            // 개인 스케줄 삭제
            handle_delete_personal_schedule($current_user, $data);
        } else {
            // 팀 스케줄 삭제
            handle_delete_team_schedule($current_user, $schedule_id);
        }
        break;
        
    default:
        error_response('Method not allowed', 405);
        break;
}

// 팀 스케줄 생성
function handle_create_team_schedule($current_user, $data) {
    // 입력 검증
    $required_fields = array('team_id', 'schedule_name', 'start_date', 'end_date');
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            error_response(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    $team_id = (int)$data['team_id'];
    $schedule_name = db_escape(trim($data['schedule_name']));
    $start_date = db_escape($data['start_date']);
    $end_date = db_escape($data['end_date']);
    $status = db_escape(isset($data['status']) ? $data['status'] : '예정');
    $user_id = $current_user['id'];
    
    // 팀 멤버 권한 확인 (팀원이거나 관리자여야 함)
    $member_check = db_fetch_one("
        SELECT tm.id, t.admin_id 
        FROM teams t
        LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = {$user_id}
        WHERE t.id = {$team_id} AND (t.admin_id = {$user_id} OR tm.user_id = {$user_id})
    ");
    
    if (!$member_check) {
        error_response('Permission denied. You must be a team member to create schedules.', 403);
    }
    
    // 날짜 유효성 검증
    if (strtotime($start_date) > strtotime($end_date)) {
        error_response('Start date cannot be later than end date');
    }
    
    // 팀 스케줄 생성
    $query = "INSERT INTO team_schedules (team_id, schedule_name, start_date, end_date, status, created_at) 
              VALUES ({$team_id}, '{$schedule_name}', '{$start_date}', '{$end_date}', '{$status}', NOW())";
    
    if (db_query($query)) {
        // 생성된 스케줄 ID 가져오기
        $result = db_fetch_one("SELECT LAST_INSERT_ID() as id");
        $new_schedule_id = $result['id'];
        
        // 생성된 스케줄 정보 반환
        $schedule = db_fetch_one("
            SELECT ts.*, t.team_name, u.name as creator_name
            FROM team_schedules ts
            JOIN teams t ON ts.team_id = t.id
            LEFT JOIN users u ON t.admin_id = u.id
            WHERE ts.id = {$new_schedule_id}
        ");
        
        success_response(array(
            'schedule' => $schedule,
            'message' => 'Team schedule created successfully'
        ), 'Team schedule created successfully', 201);
    } else {
        error_response('Failed to create team schedule', 500);
    }
}

// 개인 스케줄(세부사항) 생성
function handle_create_personal_schedule($current_user, $data) {
    // 입력 검증
    $required_fields = array('team_schedules_id', 'team_member_id', 'detail_name');
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            error_response(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    $team_schedules_id = (int)$data['team_schedules_id'];
    $team_member_id = (int)$data['team_member_id'];
    $detail_name = db_escape(trim($data['detail_name']));
    $detail_status = db_escape(isset($data['detail_status']) ? $data['detail_status'] : '미완료');
    $sort_order = (int)(isset($data['sort_order']) ? $data['sort_order'] : 0);
    $user_id = $current_user['id'];
    
    // 권한 확인: 자신의 팀 멤버 ID이거나 팀 관리자여야 함
    $permission_check = db_fetch_one("
        SELECT tm.user_id, t.admin_id, ts.schedule_name
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        JOIN team_schedules ts ON t.id = ts.team_id
        WHERE tm.id = {$team_member_id} 
        AND ts.id = {$team_schedules_id}
        AND (tm.user_id = {$user_id} OR t.admin_id = {$user_id})
    ");
    
    if (!$permission_check) {
        error_response('Permission denied. You can only create personal schedules for yourself or as team admin.', 403);
    }
    
    // 개인 스케줄 생성
    $query = "INSERT INTO personal_schedule (team_schedules_id, team_member_id, detail_name, detail_status, sort_order, created_at) 
              VALUES ({$team_schedules_id}, {$team_member_id}, '{$detail_name}', '{$detail_status}', {$sort_order}, NOW())";
    
    if (db_query($query)) {
        // 생성된 개인 스케줄 정보 반환
        $result = db_fetch_one("SELECT LAST_INSERT_ID() as id");
        $new_personal_id = $result['id'];
        
        $personal_schedule = db_fetch_one("
            SELECT ps.*, u.name as assignee_name, ts.schedule_name as team_schedule_name
            FROM personal_schedule ps
            JOIN team_members tm ON ps.team_member_id = tm.id
            JOIN users u ON tm.user_id = u.id
            JOIN team_schedules ts ON ps.team_schedules_id = ts.id
            WHERE ps.personal_schedule_id = {$new_personal_id}
        ");
        
        success_response(array(
            'personal_schedule' => $personal_schedule,
            'message' => 'Personal schedule created successfully'
        ), 'Personal schedule created successfully', 201);
    } else {
        error_response('Failed to create personal schedule', 500);
    }
}

// 특정 팀의 스케줄 목록 조회
function handle_get_team_schedules($current_user, $team_id) {
    $user_id = $current_user['id'];
    
    // 팀 멤버 권한 확인
    $member_check = db_fetch_one("
        SELECT tm.id, t.admin_id, t.team_name
        FROM teams t
        LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = {$user_id}
        WHERE t.id = {$team_id} AND (t.admin_id = {$user_id} OR tm.user_id = {$user_id})
    ");
    
    if (!$member_check) {
        error_response('Permission denied. You must be a team member to view schedules.', 403);
    }
    
    // 팀 스케줄 목록 조회
    $schedules = db_fetch_all("
        SELECT ts.*, t.team_name,
               (SELECT COUNT(*) FROM personal_schedule WHERE team_schedules_id = ts.id) as personal_count,
               (SELECT COUNT(*) FROM personal_schedule WHERE team_schedules_id = ts.id AND detail_status = '완료') as completed_count
        FROM team_schedules ts
        JOIN teams t ON ts.team_id = t.id
        WHERE ts.team_id = {$team_id}
        ORDER BY ts.start_date DESC
    ");
    
    // 각 스케줄의 진행률 계산
    foreach ($schedules as &$schedule) {
        $total = (int)$schedule['personal_count'];
        $completed = (int)$schedule['completed_count'];
        $schedule['progress_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }
    
    success_response(array(
        'schedules' => $schedules,
        'team' => array(
            'id' => $team_id,
            'name' => $member_check['team_name']
        ),
        'is_admin' => ($member_check['admin_id'] == $user_id)
    ), 'Team schedules retrieved successfully');
}

// 특정 스케줄 상세 조회 (개인 스케줄 포함)
function handle_get_schedule_detail($current_user, $schedule_id) {
    $user_id = (int)$current_user['id'];
    $schedule_id = (int)$schedule_id;
    
    // 스케줄 기본 정보 및 권한 확인
    $schedule = db_fetch_one("
        SELECT ts.*, t.team_name, t.admin_id
        FROM team_schedules ts
        JOIN teams t ON ts.team_id = t.id
        LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = $user_id
        WHERE ts.id = $schedule_id AND (t.admin_id = $user_id OR tm.user_id = $user_id)
    ");
    
    if (!$schedule) {
        error_response('Schedule not found or access denied', 404);
    }
    
    // ✅ 수정된 개인 스케줄 조회 쿼리 (assignee_id, assignee_name 포함)
    $personal_schedules = db_fetch_all("
        SELECT ps.*, tm.position, tm.role, u.name as assignee_name, u.id as assignee_id
        FROM personal_schedule ps
        JOIN team_members tm ON ps.team_member_id = tm.id
        JOIN users u ON tm.user_id = u.id
        WHERE ps.team_schedules_id = $schedule_id
        ORDER BY ps.sort_order ASC, ps.created_at ASC
    ");
    
    // 팀 멤버들 조회 (개인 스케줄 할당용)
    $team_members = db_fetch_all("
        SELECT tm.id as member_id, tm.position, tm.role, u.id, u.name
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = " . $schedule['team_id'] . "
        ORDER BY tm.joined_at ASC
    ");
    
    // 진행률 계산
    $total_personal = count($personal_schedules);
    $completed_personal = 0;
    foreach ($personal_schedules as $ps) {
        if ($ps['detail_status'] === '완료') {
            $completed_personal++;
        }
    }
    
    $schedule['personal_schedules'] = $personal_schedules;
    $schedule['team_members'] = $team_members;
    $schedule['progress'] = array(
        'total' => $total_personal,
        'completed' => $completed_personal,
        'rate' => $total_personal > 0 ? round(($completed_personal / $total_personal) * 100, 1) : 0
    );
    $schedule['is_admin'] = ($schedule['admin_id'] == $user_id);
    
    success_response(array(
        'schedule' => $schedule
    ), 'Schedule details retrieved successfully');
}

// 팀원 중심 계층적 스케줄 조회 (팀원 > 팀스케줄 > 개인스케줄)
function handle_get_member_centered_schedules($current_user, $team_id) {
    $user_id = (int)$current_user['id'];
    $team_id = (int)$team_id;
    
    // 팀 멤버 권한 확인
    $member_check = db_fetch_one("
        SELECT tm.id, t.admin_id, t.team_name
        FROM teams t
        LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.user_id = $user_id
        WHERE t.id = $team_id AND (t.admin_id = $user_id OR tm.user_id = $user_id)
    ");
    
    if (!$member_check) {
        error_response('Permission denied. You must be a team member to view schedules.', 403);
    }
    
    // 1. 팀의 모든 스케줄과 개인 작업 조회
    $all_personal_schedules = db_fetch_all("
        SELECT 
            ps.personal_schedule_id,
            ps.detail_name,
            ps.detail_status,
            ps.sort_order,
            ps.created_at,
            ts.id as team_schedule_id,
            ts.schedule_name as team_schedule_name,
            ts.start_date,
            ts.end_date,
            ts.status as team_status,
            tm.position,
            tm.role,
            u.name as assignee_name,
            u.id as assignee_id,
            u.profile_image
        FROM personal_schedule ps
        JOIN team_schedules ts ON ps.team_schedules_id = ts.id
        JOIN team_members tm ON ps.team_member_id = tm.id
        JOIN users u ON tm.user_id = u.id
        WHERE ts.team_id = $team_id
        ORDER BY u.name ASC, ts.start_date DESC, ps.sort_order ASC
    ");
    
    // 2. 팀원별로 그룹화
    $members_data = array();
    
    foreach ($all_personal_schedules as $schedule) {
        $assignee_id = $schedule['assignee_id'];
        
        // 팀원 정보 초기화
        if (!isset($members_data[$assignee_id])) {
            $members_data[$assignee_id] = array(
                'assignee_id' => $assignee_id,
                'assignee_name' => $schedule['assignee_name'],
                'position' => $schedule['position'],
                'role' => $schedule['role'],
                'profile_image' => $schedule['profile_image'],
                'team_schedules' => array(),
                'member_stats' => array(
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'pending_tasks' => 0
                )
            );
        }
        
        $team_schedule_id = $schedule['team_schedule_id'];
        
        // 팀 스케줄 정보 초기화
        if (!isset($members_data[$assignee_id]['team_schedules'][$team_schedule_id])) {
            $members_data[$assignee_id]['team_schedules'][$team_schedule_id] = array(
                'team_schedule_id' => $team_schedule_id,
                'team_schedule_name' => $schedule['team_schedule_name'],
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'team_status' => $schedule['team_status'],
                'personal_schedules' => array(),
                'schedule_stats' => array(
                    'total_tasks' => 0,
                    'completed_tasks' => 0
                )
            );
        }
        
        // 개인 스케줄 추가
        $personal_task = array(
            'personal_schedule_id' => $schedule['personal_schedule_id'],
            'detail_name' => $schedule['detail_name'],
            'detail_status' => $schedule['detail_status'],
            'sort_order' => $schedule['sort_order'],
            'created_at' => $schedule['created_at']
        );
        
        $members_data[$assignee_id]['team_schedules'][$team_schedule_id]['personal_schedules'][] = $personal_task;
        
        // 통계 업데이트
        $members_data[$assignee_id]['member_stats']['total_tasks']++;
        $members_data[$assignee_id]['team_schedules'][$team_schedule_id]['schedule_stats']['total_tasks']++;
        
        if ($schedule['detail_status'] === '완료') {
            $members_data[$assignee_id]['member_stats']['completed_tasks']++;
            $members_data[$assignee_id]['team_schedules'][$team_schedule_id]['schedule_stats']['completed_tasks']++;
        } else {
            $members_data[$assignee_id]['member_stats']['pending_tasks']++;
        }
    }
    
    // 3. 배열 형태로 변환 및 완료율 계산
    $final_data = array();
    foreach ($members_data as $member) {
        // 팀 스케줄 배열로 변환
        $team_schedules_array = array();
        foreach ($member['team_schedules'] as $team_schedule) {
            $team_schedule['completion_rate'] = $team_schedule['schedule_stats']['total_tasks'] > 0 
                ? round(($team_schedule['schedule_stats']['completed_tasks'] / $team_schedule['schedule_stats']['total_tasks']) * 100, 1)
                : 0;
            $team_schedules_array[] = $team_schedule;
        }
        
        $member['team_schedules'] = $team_schedules_array;
        
        // 멤버 전체 완료율 계산
        $member['member_stats']['completion_rate'] = $member['member_stats']['total_tasks'] > 0
            ? round(($member['member_stats']['completed_tasks'] / $member['member_stats']['total_tasks']) * 100, 1)
            : 0;
        
        $final_data[] = $member;
    }
    
    // 4. 팀 전체 통계
    $completed_tasks_count = 0;
    $unique_team_schedules = array();
    
    foreach ($all_personal_schedules as $task) {
        if ($task['detail_status'] === '완료') {
            $completed_tasks_count++;
        }
        
        // 고유한 팀 스케줄 ID 수집
        if (!in_array($task['team_schedule_id'], $unique_team_schedules)) {
            $unique_team_schedules[] = $task['team_schedule_id'];
        }
    }
    
    $team_stats = array(
        'total_members' => count($final_data),
        'total_team_schedules' => count($unique_team_schedules),
        'total_personal_tasks' => count($all_personal_schedules),
        'completed_tasks' => $completed_tasks_count
    );
    
    $team_stats['completion_rate'] = $team_stats['total_personal_tasks'] > 0
        ? round(($team_stats['completed_tasks'] / $team_stats['total_personal_tasks']) * 100, 1)
        : 0;
    
    success_response(array(
        'team_info' => array(
            'team_id' => $team_id,
            'team_name' => $member_check['team_name'],
            'is_admin' => ($member_check['admin_id'] == $user_id)
        ),
        'members_schedules' => $final_data,
        'team_stats' => $team_stats
    ), 'Member-centered schedules retrieved successfully');
}

// 내 스케줄 조회 (모든 팀의 내 할당 스케줄)
function handle_get_my_schedules($current_user, $data) {
    $user_id = $current_user['id'];
    $status_filter = isset($data['status']) ? db_escape($data['status']) : '';
    $date_filter = isset($data['date']) ? db_escape($data['date']) : '';
    
    // 기본 쿼리
    $where_conditions = array("tm.user_id = {$user_id}");
    
    // 상태 필터
    if ($status_filter) {
        $where_conditions[] = "ps.detail_status = '{$status_filter}'";
    }
    
    // 날짜 필터 (팀 스케줄 기준)
    if ($date_filter) {
        $where_conditions[] = "'{$date_filter}' BETWEEN ts.start_date AND ts.end_date";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $my_schedules = db_fetch_all("
        SELECT ps.*, ts.schedule_name as team_schedule_name, ts.start_date, ts.end_date, ts.status as team_status,
               t.team_name, t.id as team_id, tm.position, tm.role
        FROM personal_schedule ps
        JOIN team_schedules ts ON ps.team_schedules_id = ts.id
        JOIN teams t ON ts.team_id = t.id
        JOIN team_members tm ON ps.team_member_id = tm.id
        WHERE {$where_clause}
        ORDER BY ts.start_date DESC, ps.sort_order ASC
    ");
    
    success_response(array(
        'my_schedules' => $my_schedules,
        'filters' => array(
            'status' => $status_filter,
            'date' => $date_filter
        ),
        'total_count' => count($my_schedules)
    ), 'My schedules retrieved successfully');
}

// 팀 스케줄 수정
function handle_update_team_schedule($current_user, $schedule_id, $data) {
    if (!$schedule_id) {
        error_response('Schedule ID is required');
    }
    
    $user_id = $current_user['id'];
    
    // 권한 확인 (팀 관리자만 팀 스케줄 수정 가능)
    $schedule = db_fetch_one("
        SELECT ts.*, t.admin_id
        FROM team_schedules ts
        JOIN teams t ON ts.team_id = t.id
        WHERE ts.id = {$schedule_id}
    ");
    
    if (!$schedule) {
        error_response('Schedule not found', 404);
    }
    
    if ($schedule['admin_id'] != $user_id) {
        error_response('Permission denied. Only team admin can update team schedules.', 403);
    }
    
    // 업데이트할 필드들
    $update_fields = array();
    
    if (isset($data['schedule_name']) && !empty($data['schedule_name'])) {
        $schedule_name = db_escape(trim($data['schedule_name']));
        $update_fields[] = "schedule_name = '{$schedule_name}'";
    }
    
    if (isset($data['start_date']) && !empty($data['start_date'])) {
        $start_date = db_escape($data['start_date']);
        $update_fields[] = "start_date = '{$start_date}'";
    }
    
    if (isset($data['end_date']) && !empty($data['end_date'])) {
        $end_date = db_escape($data['end_date']);
        $update_fields[] = "end_date = '{$end_date}'";
    }
    
    if (isset($data['status'])) {
        $status = db_escape($data['status']);
        $update_fields[] = "status = '{$status}'";
    }
    
    if (empty($update_fields)) {
        error_response('No valid fields to update');
    }
    
    $update_clause = implode(', ', $update_fields);
    $query = "UPDATE team_schedules SET {$update_clause} WHERE id = {$schedule_id}";
    
    if (db_query($query)) {
        // 업데이트된 스케줄 정보 반환
        $updated_schedule = db_fetch_one("
            SELECT ts.*, t.team_name
            FROM team_schedules ts
            JOIN teams t ON ts.team_id = t.id
            WHERE ts.id = {$schedule_id}
        ");
        
        success_response(array(
            'schedule' => $updated_schedule
        ), 'Team schedule updated successfully');
    } else {
        error_response('Failed to update team schedule', 500);
    }
}

// 개인 스케줄 수정
function handle_update_personal_schedule($current_user, $data) {
    if (empty($data['personal_schedule_id'])) {
        error_response('Personal schedule ID is required');
    }
    
    $personal_id = (int)$data['personal_schedule_id'];
    $user_id = $current_user['id'];
    
    // 권한 확인 (자신의 개인 스케줄이거나 팀 관리자)
    $permission_check = db_fetch_one("
        SELECT ps.*, tm.user_id, t.admin_id
        FROM personal_schedule ps
        JOIN team_members tm ON ps.team_member_id = tm.id
        JOIN teams t ON tm.team_id = t.id
        WHERE ps.personal_schedule_id = {$personal_id}
        AND (tm.user_id = {$user_id} OR t.admin_id = {$user_id})
    ");
    
    if (!$permission_check) {
        error_response('Permission denied. You can only update your own personal schedules or as team admin.', 403);
    }
    
    // 업데이트할 필드들
    $update_fields = array();
    
    if (isset($data['detail_name']) && !empty($data['detail_name'])) {
        $detail_name = db_escape(trim($data['detail_name']));
        $update_fields[] = "detail_name = '{$detail_name}'";
    }
    
    if (isset($data['detail_status'])) {
        $detail_status = db_escape($data['detail_status']);
        $update_fields[] = "detail_status = '{$detail_status}'";
    }
    
    if (isset($data['sort_order'])) {
        $sort_order = (int)$data['sort_order'];
        $update_fields[] = "sort_order = {$sort_order}";
    }
    
    if (empty($update_fields)) {
        error_response('No valid fields to update');
    }
    
    $update_clause = implode(', ', $update_fields);
    $query = "UPDATE personal_schedule SET {$update_clause} WHERE personal_schedule_id = {$personal_id}";
    
    if (db_query($query)) {
        success_response(array(), 'Personal schedule updated successfully');
    } else {
        error_response('Failed to update personal schedule', 500);
    }
}

// 스케줄 상태 업데이트 (빠른 상태 변경용)
function handle_update_schedule_status($current_user, $data) {
    if (empty($data['personal_schedule_id']) || empty($data['status'])) {
        error_response('Personal schedule ID and status are required');
    }
    
    $personal_id = (int)$data['personal_schedule_id'];
    $status = db_escape($data['status']);
    $user_id = $current_user['id'];
    
    // 권한 확인
    $permission_check = db_fetch_one("
        SELECT tm.user_id, t.admin_id
        FROM personal_schedule ps
        JOIN team_members tm ON ps.team_member_id = tm.id
        JOIN teams t ON tm.team_id = t.id
        WHERE ps.personal_schedule_id = {$personal_id}
        AND (tm.user_id = {$user_id} OR t.admin_id = {$user_id})
    ");
    
    if (!$permission_check) {
        error_response('Permission denied', 403);
    }
    
    $query = "UPDATE personal_schedule SET detail_status = '{$status}' WHERE personal_schedule_id = {$personal_id}";
    
    if (db_query($query)) {
        success_response(array(
            'status' => $status
        ), 'Schedule status updated successfully');
    } else {
        error_response('Failed to update schedule status', 500);
    }
}

// 팀 스케줄 삭제
function handle_delete_team_schedule($current_user, $schedule_id) {
    if (!$schedule_id) {
        error_response('Schedule ID is required');
    }
    
    $user_id = $current_user['id'];
    
    // 권한 확인 (팀 관리자만)
    $schedule = db_fetch_one("
        SELECT ts.schedule_name, t.admin_id
        FROM team_schedules ts
        JOIN teams t ON ts.team_id = t.id
        WHERE ts.id = {$schedule_id}
    ");
    
    if (!$schedule) {
        error_response('Schedule not found', 404);
    }
    
    if ($schedule['admin_id'] != $user_id) {
        error_response('Permission denied. Only team admin can delete team schedules.', 403);
    }
    
    // 관련 개인 스케줄 먼저 삭제
    db_query("DELETE FROM personal_schedule WHERE team_schedules_id = {$schedule_id}");
    
    // 팀 스케줄 삭제
    if (db_query("DELETE FROM team_schedules WHERE id = {$schedule_id}")) {
        success_response(array(
            'schedule_name' => $schedule['schedule_name']
        ), 'Team schedule and related personal schedules deleted successfully');
    } else {
        error_response('Failed to delete team schedule', 500);
    }
}

// 개인 스케줄 삭제
function handle_delete_personal_schedule($current_user, $data) {
    if (empty($data['personal_schedule_id'])) {
        error_response('Personal schedule ID is required');
    }
    
    $personal_id = (int)$data['personal_schedule_id'];
    $user_id = $current_user['id'];
    
    // 권한 확인
    $permission_check = db_fetch_one("
        SELECT ps.detail_name, tm.user_id, t.admin_id
        FROM personal_schedule ps
        JOIN team_members tm ON ps.team_member_id = tm.id
        JOIN teams t ON tm.team_id = t.id
        WHERE ps.personal_schedule_id = {$personal_id}
        AND (tm.user_id = {$user_id} OR t.admin_id = {$user_id})
    ");
    
    if (!$permission_check) {
        error_response('Permission denied', 403);
    }
    
    if (db_query("DELETE FROM personal_schedule WHERE personal_schedule_id = {$personal_id}")) {
        success_response(array(
            'detail_name' => $permission_check['detail_name']
        ), 'Personal schedule deleted successfully');
    } else {
        error_response('Failed to delete personal schedule', 500);
    }
}
?>