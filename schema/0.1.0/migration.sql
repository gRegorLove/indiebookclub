ALTER TABLE `entries` CHANGE COLUMN `canonical_url` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `users` ADD COLUMN `revocation_endpoint` varchar(255) NOT NULL DEFAULT '' AFTER `token_endpoint`;

