ALTER TABLE `users` ADD COLUMN `default_visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public' AFTER `last_micropub_response`;

ALTER TABLE `entries` ADD COLUMN `visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public' AFTER `category`;

