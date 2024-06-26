# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.3] 2024-06-15
### Changed
- Added client metadata JSON endpoint and updated client_id in IndieAuth requests
- See https://github.com/indieweb/indieauth/issues/133

## [0.1.2] 2024-04-07
### Added
- Add `draft` scope and `post-status` support [#21](https://github.com/gRegorLove/indiebookclub/issues/21)
- Show an error if a Micropub user did not grant `create` scope [#21]

### Changed
- Persist /new query parameters [#22](https://github.com/gRegorLove/indiebookclub/issues/22)

## [0.1.1] - 2023-12-02
### Added
- Year in Review page for indiebookclub as a whole, e.g. `/review/2023`
  - Page is set to start being available November 30 each year.
  - Stats are updated and cached daily through the new year.
  - Stats are only for public posts. Private and unlisted posts are not included.

## [0.1.0] - 2022-11-14
### Added
- Micropub re-try for posts that failed or were not published previously [#13](https://github.com/gRegorLove/indiebookclub/issues/13)
- "Add to my list" shortcut link on posts [#3](https://github.com/gRegorLove/indiebookclub/issues/3)
- Published Date and Time field to allow backdating [#12](https://github.com/gRegorLove/indiebookclub/issues/12)
- Add profile name and photo [#19](https://github.com/gRegorLove/indiebookclub/issues/19)

### Changed
- Updated IndieAuth\Client, now supports IndieAuth Server Metadata
- Migrated templates to Twig
- Refactored and modernized programming
- Removed jQuery https://youmightnotneedjquery.com/

## [0.0.3] - 2021-12-03
### Changed
- Updated IndieAuth\Client usage, now supports PKCE
- Updated page header to indicate domain you are signed in as, profile link, sign out link, and new post button

### Added
- Support for micropub `visibility` property [#4](https://github.com/gRegorLove/indiebookclub/issues/4)
- Support for micropub delete after re-authorizing and granting `delete` scope [#13](https://github.com/gRegorLove/indiebookclub/issues/13)

