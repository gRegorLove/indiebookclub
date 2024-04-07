# indiebookclub
indiebookclub is a simple app for tracking books you are reading https://indiebookclub.biz

# Installation
indiebookclub requires PHP, MySQL, and Composer. It is intended to be installed at the root of a domain or sub-domain.

* Set the domain’s document root to the `/public` directory
* Configure the server to route requests through `/public/index.php` if they don’t match a file
  * If you are running Apache, do this by renaming `/public/htaccess.txt` to `/public/.htaccess`
* Set up MySQL tables using `/schema/schema.sql`
* Run `composer install`
* Rename `/.env.example` to `/.env` and fill in your email, hostname, base URL, and MySQL connection information
  * Optionally specify the LOG_DIR if you want logs stored somewhere other than `/logs`

## Environment Variables
In development, environment variables are loaded from the `.env` file on each request. In production, it is recommended to set/cache them in the environment to avoid that overhead.

To cache environment variables, run at the command line:
`php ./deploy.php`

This will cache the environment variables in the file `/app/env.php`.

Once cached, if you make updates to `.env`, you will need to run this command again to update the cache. You can also delete this cache file and the application will revert to development mode, loading directly from the `.env` file on each request.

Advanced: If your hosting lets you set up environment variables another way, you can look in `/app/config.php` to change the code that checks for that cached environment file.

## Maintenance Mode
Maintenance mode displays a maintenance message in place of all pages on the app. You can specify an IP address that is able to browse the site normally.

To enable maintenance mode, update `/app/settings.php`. Set `offline` to `true` and `developer_ip` to your IP address.

## Development Mode
Development mode displays all error messages and restricts login to a single domain. Unlike maintenance mode, public pages remain visible and do not display a maintenance message.

To enable development mode, set the environment variable `APP_ENV` to `dev` (or anything other than `production`). Then update `/app/settings.php` and update `developer_domains` with the domain(s) you want to enable logins for.

# Changelog
* [Changelog](CHANGELOG.md)

# Credits
* Inspired by and using open source code from [Aaron Parecki’s](https://aaronparecki.com) [Teacup](https://teacup.p3k.io/) and [Quill](https://quill.p3k.io/).
* “[Book](https://thenounproject.com/icon/1727889/)” icon by Beth Bolton from [the Noun Project](http://thenounproject.com/).
* Naming inspiration: [Marty McGuire](https://martymcgui.re/)

# License
Copyright 2018 by gRegor Morrill. Licensed under the MIT license https://opensource.org/licenses/MIT

This project also uses code with the following copyright and licenses:
* [PhpIsbn library](https://github.com/mwhite/php-isbn) Copyright 2012 by Michael White. Licensed under the MIT license.
* [Teacup](https://teacup.p3k.io/) Copyright 2014 by Aaron Parecki. Licensed under the Apache License, Version 2.0.
