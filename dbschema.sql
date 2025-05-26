-- UTF-8이 적용된 테이블 생성 스크립트

-- 기존 테이블들 삭제 (순서 중요 - 외래키 때문에)
DROP TABLE IF EXISTS `schedule_details`;
DROP TABLE IF EXISTS `personal_schedules`;
DROP TABLE IF EXISTS `team_schedules`;
DROP TABLE IF EXISTS `meeting_records`;
DROP TABLE IF EXISTS `team_members`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `users`;

-- UTF-8로 테이블 생성
CREATE TABLE `users` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `userid` varchar(50) UNIQUE NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) UNIQUE NOT NULL,
  `name` varchar(50) NOT NULL,
  `profile_image` varchar(255) DEFAULT '/images/default-avatar.png',
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `teams` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_name` varchar(100) NOT NULL,
  `admin_id` integer NOT NULL COMMENT '팀 관리자',
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `team_members` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_id` integer NOT NULL,
  `user_id` integer NOT NULL,
  `position` varchar(20) COMMENT '팀장, 팀원',
  `role` varchar(20) COMMENT '프론트엔드, 백엔드',
  `joined_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `team_schedules` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_id` integer NOT NULL,
  `schedule_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) COMMENT '예정, 진행중, 완료',
  `created_by` integer NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `personal_schedules` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_schedule_id` integer NOT NULL COMMENT '소속된 팀 스케줄',
  `user_id` integer NOT NULL COMMENT '담당자',
  `schedule_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) COMMENT '예정, 진행중, 완료',
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `schedule_details` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `personal_schedule_id` integer NOT NULL,
  `detail_name` varchar(100) NOT NULL,
  `detail_status` varchar(10) COMMENT '미완료, 완료',
  `sort_order` integer DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `meeting_records` (
  `id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_id` integer NOT NULL,
  `title` varchar(200) NOT NULL,
  `secretary_id` integer NOT NULL COMMENT '서기',
  `meeting_date` datetime NOT NULL,
  `main_agenda` text NOT NULL COMMENT '주요 안건',
  `detailed_content` text COMMENT '세부내용',
  `attendees` text COMMENT '참석자 (쉼표로 구분된 이름들)',
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

-- 인덱스 생성
CREATE UNIQUE INDEX `team_members_index_0` ON `team_members` (`team_id`, `user_id`);

-- 외래키 제약 조건 추가
ALTER TABLE `teams` ADD FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);
ALTER TABLE `team_members` ADD FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);
ALTER TABLE `team_members` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `team_schedules` ADD FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);
ALTER TABLE `team_schedules` ADD FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `personal_schedules` ADD FOREIGN KEY (`team_schedule_id`) REFERENCES `team_schedules` (`id`);
ALTER TABLE `personal_schedules` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `schedule_details` ADD FOREIGN KEY (`personal_schedule_id`) REFERENCES `personal_schedules` (`id`);
ALTER TABLE `meeting_records` ADD FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);
ALTER TABLE `meeting_records` ADD FOREIGN KEY (`secretary_id`) REFERENCES `users` (`id`);