# Tests & coverage

![Language](https://img.shields.io/badge/language-English-blue)

The library is validated by a **PHPUnit unit-test suite**, run on every commit and in CI. There are no "live" tests: everything is testable in isolation, against the local filesystem (real temporary directories + `vfsStream`).

The condensed contributor workflow lives in [CONTRIBUTING.md](../../CONTRIBUTING.md); this page is the detailed reference.

## Running the tests

The suite lives in [`tests/`](../../tests) and runs with:

```shell
composer test                                        # = ./vendor/bin/phpunit
./vendor/bin/phpunit --filter MakeAbsoluteTest       # a single case
```

Configuration: [phpunit.xml](../../phpunit.xml). Key points:

- **Coverage scope**: `./src` only (the `<source>` tag).
- **Strict mode**: `failOnWarning`, `failOnRisky`, `failOnSkipped`, `failOnIncomplete`, `beStrictAboutOutputDuringTests`… A "risky" test (no assertion, producing output, triggering a PHP warning…) **fails** the suite. This is intentional: a test that checks nothing protects nothing.

### A *skip* is a failure, so none are written

`failOnSkipped` has a consequence that is easy to forget: a test that skips makes the suite fail.
The rule that follows, set in 1.5.0: **where the library has something to say without the tool,
the test asserts what it says instead of skipping.**

The case that forced it: `ext-bz2` is a *suggestion* of this package, and on a Debian with
`php-cli` and no `php-bz2`, six `Bzip2FileTest` tests failed — ever since the extension became a
suggestion rather than a requirement. Skipping them would have fixed nothing, `failOnSkipped`
would have counted them as failures all the same. What is true on such a host is that
`bzip2File()` and `bunzip2File()` **refuse by naming the extension** instead of dying on an
undefined `bzopen()`. So that is what those tests assert there, and the normal behaviour here —
the suite is green in both environments without a single *skip*. Verified by running the whole
suite under `php -d disable_functions=bzopen,…`.

Two families of *skips* remain, and neither fires on this project's own platforms: Windows
(the permission tests) and a host with no GNU tar.

> **On macOS, `composer test` can exit with an error while nothing is broken.** The tests that
> exercise the binary `tar` engine — [`TarEngineTest`](../../tests/oihana/files/archive/tar/TarEngineTest.php)
> and, since 1.5.0, the extraction ones — are *skipped* with no GNU tar on the machine, so the
> output reads "OK, but some tests were skipped!" and Composer then reports an `error code 1`.
> Apple's `/usr/bin/tar` is `bsdtar`, which the library deliberately refuses
> ([why](archive/tar-engine.md#which-binary-and-why-not-all-of-them)). A `brew install gnu-tar` is
> enough. CI runs on Linux and is unaffected.

## What is tested, and how

The library is made of **standalone functions** plus a few classes; three testability tiers emerge:

| Tier | Target | Technique |
|---|---|---|
| 1 | **Pure** functions (`path/**`, enums, MIME helpers) | Input → expected output, often via a data provider. No mocks. |
| 2 | **Filesystem** operations (`makeDirectory`, `findFiles`, `tar`/`untar`, `OpenSSLFileEncryption`…) | Real temporary directories under `sys_get_temp_dir()` **and** [`vfsStream`](https://github.com/bovigo/vfsStream) to simulate permissions. Cleanup in `tearDown`. |
| 3 | **Error paths** | Triggered deterministically: `chmod 0444/0555` (non-writable dir/file), `chown`/`chgrp` to a non-existent user, writing to a directory path, deliberately corrupted payloads. Permission-dependent tests are skipped on Windows. |

> **Characterization tests.** When covering existing code, we write tests that describe what the code **actually does**, branch by branch (`if` / `else` / `match`). This precision regularly surfaces real bugs or inconsistencies (a wrong docblock example, an inverted guard, dead features…). **Golden rule**: if a surprising behaviour might be relied on downstream, **freeze it in a test** and flag it — never change a public API without explicit validation. A real bug found along the way is fixed in a **separate** `fix(...)` commit (with a CHANGELOG entry when the impact is visible), never buried in a test commit.

## Code coverage

PHPUnit measures which lines of `./src` are executed by the suite. You must **enable Xdebug's coverage mode** (or PCOV); otherwise PHPUnit prints `No tests executed!` and an `XDEBUG_MODE=coverage … has to be set` warning. The `composer` scripts below set the environment variable for you:

```shell
composer coverage       # suite + coverage: text in the terminal, Clover + HTML under build/coverage/
composer coverage:md    # regenerate build/coverage/COVERAGE.md (Markdown summary, worst areas first)
```

Output goes under `build/coverage/` — **gitignored, never committed**: a numbers snapshot goes stale at the next commit and pollutes diffs. We regenerate on demand. The Clover → Markdown converter lives in [`tools/clover-to-markdown.php`](../../tools/clover-to-markdown.php).

### Trend between runs

Each generation timestamps the report and writes a snapshot into `build/coverage/history.json` (also gitignored). On the next run, the summary compares against the **previous recorded run** and shows a per-metric delta: `▲ +0.14 pts (+12 lines)` / `▼ -0.30 pts (-5 methods)` / `= ±0.00 pts (+0 lines)`. The log is bounded to the last 50 runs and is **purely local** (for shared tracking, publish the report via CI rather than committing it).

### Reading the report

- **Lines** = the reference metric (% of lines executed).
- An empty bar = code **never tested** → a potential undetected bug.
- ⚠️ **100% ≠ zero bugs.** A line "walked through" without a solid assertion is *covered* but not truly *verified*. We therefore aim for tests that **assert a precise result**, not ones that merely pass through the code.

## Coverage policy

The principle is: **test everything reachable**. When a line is uncovered, there are only two outcomes:

1. **Make it reachable and test it** (the default) — including via a degenerate configuration or a crafted input.
2. If it is **genuinely unreachable defensive code** under test, first prefer to **remove/simplify** it (refactor); only as a last resort annotate it with a **bare** `@codeCoverageIgnore` directive, preceded by a comment explaining *why* the line is unreachable.

Considered unreachable and annotated, for example:

- **OS-specific branches** (e.g. a Windows absolute path while CI runs on Linux);
- **TOCTOU** guards (a `fopen`/`file_get_contents` that would only fail after a successful `assertFile()`);
- calls that **never fail** in the required environment (`finfo_open`, `random_bytes`, `openssl_encrypt` with valid parameters, `Phar::canCompress` with `zlib`/`bz2` present);
- **defense-in-depth** guards already neutralized by the underlying layer (e.g. `PharData` sanitises `..` entries, making `untar`'s path-traversal guard untriggerable through a PharData-created archive — since 1.5.0 one test builds the hostile archive **by hand**, `ustar` header and checksum included, since GNU tar strips `../` as it writes).

State at **2026-06-09**: **100% of lines** (1416 / 1416) and **100% of methods** (38 / 38), **636 passing tests**.

## Continuous integration

The [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) workflow runs, on **PHP 8.4** (extensions `fileinfo`, `openssl`, `posix`, `zlib`, `sodium`):

1. `composer validate --strict`;
2. dependency installation (with a Composer cache);
3. `vendor/bin/phpunit`.

Coverage is not measured in CI (`coverage: none`): it is a local, on-demand check.

## See also

- [Security](security.md) — security boundary, covered / not-covered threats.
- [Tips & pitfalls](tips.md) — Windows paths, symlinks, permissions, encoding.
- [CONTRIBUTING.md](../../CONTRIBUTING.md) — condensed contributor guide.
