# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands
- Install: `task install` or `composer install`
- Lint: `task lint` (dry-run) or `task fixcs` (fix issues)
- Static analysis: `task phpstan`
- Run all tests: `task test`
- Run single test: `task test "tests/Unit/Path/To/TestFile.php"` or specific method with `--filter=methodName`
- Verify (lint+tests): `task verify`
- Performance tests: `task perf:test "scenario-name.js"`
- Always run `task verify` before committing to ensure code quality
- Generate proto artifacts: `task proto:gen:all`

## Code Style
- PHP 8.4, strict typing required (`declare(strict_types=1)`)
- PSR-4 autoloading: `App\` namespace for src/ and generated proto files
- @PER-CS2.0 and PhpCsFixer ruleset with custom modifications
- PHPStan level 10 for static analysis
- Classes should be final when possible
- One-space concatenation (`$a . $b`)
- Import ordering: classes, functions, constants
- No global namespace imports
- Trailing commas in multiline arrays, arguments and parameters
- Comments only when necessary and in English (not Russian)
- Comments should explain "why", not "what" or "how" when the code is complex
- Use `self::assert*` instead of `$this->assert*` in PHPUnit tests
- Commit messages should be short, concise and written in English

## CI/CD
- GitHub Actions are configured for CI/CD
- CI workflow runs on every push/PR to master branch:
  - Code style checking
  - Static analysis
  - Tests
  - Proto artifacts generation
- Release workflow creates distributable package when a new release is created
- Never disable checks or bypass CI workflows
- Fix CI issues locally before pushing to remote