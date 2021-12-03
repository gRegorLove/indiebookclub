CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('micropub','local') NOT NULL,
  `url` varchar(255) NOT NULL,
  `profile_slug` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `photo_url` varchar(255) NOT NULL DEFAULT '',
  `authorization_endpoint` varchar(255) NOT NULL DEFAULT '',
  `token_endpoint` varchar(255) NOT NULL DEFAULT '',
  `micropub_endpoint` varchar(255) NOT NULL DEFAULT '',
  `micropub_media_endpoint` varchar(255) NOT NULL DEFAULT '',
  `supported_visibility` text NOT NULL,
  `token_scope` varchar(255) NOT NULL DEFAULT '',
  `micropub_success` tinyint(4) DEFAULT '0',
  `last_micropub_response` text,
  `default_visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public',
  `date_created` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `PROFILESLUG` (`profile_slug`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `entries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `published` datetime DEFAULT NULL,
  `tz_offset` int(11) DEFAULT NULL,
  `read_status` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `title` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `authors` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `isbn` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `doi` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `category` varchar(255) NOT NULL DEFAULT '',
  `visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public',
  `content` text CHARACTER SET utf8,
  `canonical_url` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `micropub_success` tinyint(4) DEFAULT NULL,
  `micropub_response` text CHARACTER SET utf8,
  PRIMARY KEY (`id`),
  KEY `ISBN` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `books` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `isbn` varchar(20) NOT NULL DEFAULT '',
  `entry_count` int(10) unsigned NOT NULL DEFAULT '0',
  `first_user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ISBN` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

