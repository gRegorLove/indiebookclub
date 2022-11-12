# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2022-11-??
### Added
- Micropub re-try for posts that failed or were not published previously [#13](https://github.com/gRegorLove/indiebookclub/issues/13)
- "Add to my list" shortcut link on posts [#3](https://github.com/gRegorLove/indiebookclub/issues/3)
- Published Date and Time field to allow backdating [#12](https://github.com/gRegorLove/indiebookclub/issues/12)
- Add profile name and photo [#19](https://github.com/gRegorLove/indiebookclub/issues/19)

### Changed
- Updated IndieAuth\Client, now supports IndieAuth Server Metadata
- Migrated templates to Twig
- Refactored and modernized programming

## [0.0.3] - 2021-12-03
### Changed
- Updated IndieAuth\Client usage, now supports PKCE
- Updated page header to indicate domain you are signed in as, profile link, sign out link, and new post button

### Added
- Support for micropub `visibility` property [#4](https://github.com/gRegorLove/indiebookclub/issues/4)
- Support for micropub delete after re-authorizing and granting `delete` scope [#13](https://github.com/gRegorLove/indiebookclub/issues/13)

