ALTER TABLE `entries` ADD COLUMN `visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public' AFTER `category`;

ALTER TABLE `users` ADD COLUMN `supported_visibility` text NOT NULL AFTER `micropub_media_endpoint`;
ALTER TABLE `users` ADD COLUMN `default_visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public' AFTER `last_micropub_response`;

