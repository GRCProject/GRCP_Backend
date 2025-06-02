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
  `created_at` timestamp DEFAULT current_timestamp
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `personal_schedule` (
  `personal_schedule_id` integer PRIMARY KEY AUTO_INCREMENT,
  `team_schedules_id` integer NOT NULL,
  `team_member_id` integer NOT NULL,
  `detail_name` varchar(100) NOT NULL,
  `detail_status` varchar(10) COMMENT '미완료, 완료',
  `created_at` timestamp DEFAULT current_timestamp,
  `sort_order` integer DEFAULT 0
) CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE UNIQUE INDEX `team_members_index_0` ON `team_members` (`team_id`, `user_id`);

ALTER TABLE `teams` ADD FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

ALTER TABLE `team_members` ADD FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

ALTER TABLE `team_members` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `team_schedules` ADD FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

ALTER TABLE `personal_schedule` ADD FOREIGN KEY (`team_schedules_id`) REFERENCES `team_schedules` (`id`);

ALTER TABLE `personal_schedule` ADD FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`);
