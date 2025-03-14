# Changelog

All notable changes will be documented in this file following the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) 
format. This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.1] - 2025-03-04

## [0.6.0] - 2025-03-04

## [0.5.0] - 2024-07-10

### Added

-   Added support for `$snowflake->toDateTime()` and `$snowflake->toCarbon()` which allows you to get the timestamp associated with the ID.
-   Added `firstForTimestamp($timestamp)` method that lets you create an ID that corresponds to the lowest possible ID at that timestamp (useful for querying)
-   Added Livewire synths

### Changed

-   Bits no longer follows `Carbon::setTestNow` and instead provides its own `setTestNow` method ([see reasoning](https://github.com/glhd/bits/pull/8)).
-   Added `.gitattributes` to minimize bundle size

## [0.4.1] - 2024-03-25

## [0.4.0] - 2024-03-12

## [0.3.0] - 2024-02-15

### Added

-   Added a `snowflake_id()` helper method to avoid `Snowflake::make()->id()` calls everywhere

### Changed

-   Improved exception message when trying to set epoch to a future date

## [0.2.0] - 2023-11-13

## [0.1.1] - 2023-07-09

## [0.1.0] - 2023-07-05

## [0.0.4] - 2023-07-03

## [0.0.3] - 2023-07-03

## [0.0.2] - 2023-07-02

## [0.0.1] - 2023-07-02

## [0.0.1]

# Keep a Changelog Syntax

-   `Added` for new features.
-   `Changed` for changes in existing functionality.
-   `Deprecated` for soon-to-be removed features.
-   `Removed` for now removed features.
-   `Fixed` for any bug fixes. 
-   `Security` in case of vulnerabilities.

[Unreleased]: https://github.com/glhd/bits/compare/0.6.1...HEAD

[0.6.1]: https://github.com/glhd/bits/compare/0.6.0...0.6.1

[0.6.0]: https://github.com/glhd/bits/compare/0.5.0...0.6.0

[0.5.0]: https://github.com/glhd/bits/compare/0.4.1...0.5.0

[0.4.1]: https://github.com/glhd/bits/compare/0.4.0...0.4.1

[0.4.0]: https://github.com/glhd/bits/compare/0.3.0...0.4.0

[0.3.0]: https://github.com/glhd/bits/compare/0.2.0...0.3.0

[0.2.0]: https://github.com/glhd/bits/compare/0.1.1...0.2.0

[0.1.1]: https://github.com/glhd/bits/compare/0.1.0...0.1.1

[0.1.0]: https://github.com/glhd/bits/compare/0.0.4...0.1.0

[0.0.4]: https://github.com/glhd/bits/compare/0.0.3...0.0.4

[0.0.3]: https://github.com/glhd/bits/compare/0.0.2...0.0.3

[0.0.2]: https://github.com/glhd/bits/compare/0.0.1...0.0.2

[0.0.1]: https://github.com/glhd/bits/compare/0.0.1...0.0.1

[0.0.1]: https://github.com/glhd/bits/compare/0.0.1...0.0.1
