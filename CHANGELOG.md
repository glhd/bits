# Changelog

All notable changes will be documented in this file following the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) 
format. This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

-   Datacenter and worker IDs are now claimed from the cache at runtime, so Bits works on Lambda, Vapor, Cloud Run and anywhere else a process has no ID assigned to it up front. Each process takes an unused pair, renews it while it runs, and hands it back when it exits.
-   Added the `ResolvesIdentities` contract, along with `CacheIdentityResolver` (claims from the cache), `FixedIdentityResolver` (keeps the previous behavior) and an `IdentityResolver` base class for sourcing IDs from anywhere else.
-   Added a `bits.identity` config block (`driver`, `store`, `ttl`, `prefix`, `max_probes`, `attempts`) and a `bits.sequence_resolver` setting.
-   Setting only one of `BITS_DATACENTER_ID` / `BITS_WORKER_ID` now pins that one and claims the other.
-   Added `SegmentType::Pad` and `Segment::pad()` for bits a configuration reserves but never fills, and `Configuration::idSegments()` for the segments an identity resolver provides values for.
-   Added `WorkerIds::second()`.
-   Factories expose `identityResolver()`, `workerIds()`, and `datacenterId()` / `workerId()` / `machineId()` / `ids()`.
-   Added `SnowflakeFactory::withIdentities()`, `SonyflakeFactory::withIdentities()` and `GenericFactory::withIdentities()`.

### Changed

-   **Breaking:** with no `BITS_DATACENTER_ID` or `BITS_WORKER_ID` set, Bits now claims a pair from the cache instead of picking a random one. Set `BITS_IDENTITY_DRIVER=fixed` for the old behavior. Claiming needs a cache store with an atomic `add` (redis, memcached, dynamodb, database or file); the `array` store cannot keep two processes apart. Identities are not claimed while running tests.
-   **Breaking:** `BitsFactory::__construct()` takes a `ResolvesIdentities`. Subclasses of `BitsFactory` need updating; `new SnowflakeFactory($epoch, $datacenter_id, $worker_id, $config, $sequence)` and the equivalent Sonyflake and Generic calls still work, by position and by name.
-   **Breaking:** `Configuration` gained `idSegments()`. Configurations that extend `GenericConfig` are unaffected.
-   **Breaking:** the leading bit of `SnowflakesConfig` is now `SegmentType::Pad` rather than `SegmentType::Id`, so it is no longer counted as part of the worker ID space. This affects code that matches on `SegmentType` or calls `positionOf(SegmentType::Id)`.
-   **Breaking:** `$factory->datacenter_id`, `->worker_id`, `->machine_id` and `->ids` are resolved through `__get()` now that their values are resolved lazily. Reading them still works; reflecting over them does not.
-   **Breaking:** the ID segment of `SonyflakesConfig` is now labelled `machine` rather than `datacenter`, matching `Sonyflake::$machine_id` and the format itself — Sonyflakes have no datacenter. This changes the wording of out-of-range errors ("Machines must be an integer between 0 and 65535"), and the label used to pin a Sonyflake machine ID with `IdentitySpace` (`['machine' => 42]`).
-   A process holding its own claimed identity uses `InMemorySequenceResolver`, since nothing else can be using that identity. Configured (and so possibly shared) IDs keep using `CacheSequenceResolver`.

## [0.6.2] - 2026-03-23

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

[Unreleased]: https://github.com/glhd/bits/compare/0.6.2...HEAD

[0.6.2]: https://github.com/glhd/bits/compare/0.6.1...0.6.2

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
