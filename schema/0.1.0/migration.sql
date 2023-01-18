ALTER TABLE `entries` CHANGE COLUMN `canonical_url` `canonical_url` VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE `users` ADD COLUMN `revocation_endpoint` VARCHAR(255) NOT NULL DEFAULT '' AFTER `token_endpoint`;

