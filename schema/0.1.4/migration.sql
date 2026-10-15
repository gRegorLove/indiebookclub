ALTER TABLE `entries` ADD COLUMN `image` VARCHAR(2000) NOT NULL DEFAULT '' AFTER `visibility`;
ALTER TABLE `entries` CHANGE COLUMN `canonical_url` `canonical_url` VARCHAR(2000) NOT NULL DEFAULT '';

