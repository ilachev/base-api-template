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

## Architecture
- Clean Architecture approach with strict separation of concerns
- Three primary layers:
  - **Domain**: Pure business logic and domain models
  - **Application**: Use cases, mappers, and coordination between layers
  - **Infrastructure**: Framework and external systems integration
- Business logic should reside in the Domain layer, never in Infrastructure
- Application layer coordinates between Domain and Infrastructure
- Handlers should be thin adapters delegating to Domain services
- Use Mappers (not Builders or DTOs) for transforming between layers
- Domain objects should never depend on infrastructure or protocol-specific types
- Infrastructure components (like Hydrator) should be used by mappers to transform data
- Each layer should have its own tests focused on its responsibilities
- Do not use mocks for internal services - use real implementations in tests

## Performance and Memory Management
- Application runs on RoadRunner server - be careful with static caches and memory management
- Avoid using static arrays that grow indefinitely, use bounded caches or reset mechanisms
- Be careful with circular references that prevent garbage collection
- All services should be stateless when possible
- Reset any accumulated state between requests where needed
- Consider memory impacts of caching in long-running processes
- Prefer limited, time-bound, or LRU caches over unbounded storage
- Test memory usage with load tests before production deployment
- Never ignore static analysis warnings - fix underlying issues in code instead
- Use correct types to prevent type-related static analysis warnings
- Avoid relying on PHPDoc types for runtime conditions (use instanceof checks instead)

## Code Style
- PHP 8.4, strict typing required (`declare(strict_types=1)`)
- PSR-4 autoloading: `App\` namespace for src/ and generated proto files
- @PER-CS2.0 and PhpCsFixer ruleset with custom modifications
- PHPStan level 10 for static analysis
- Classes should be final when possible
- One-space concatenation (`$a . $b`)
- Import ordering: classes, functions, constants
- No global namespace imports
- No "Interface" suffix in interface names (use `Repository` not `RepositoryInterface`)
- Trailing commas in multiline arrays, arguments and parameters
- Comments only when necessary and in English (not Russian)
- Comments should explain "why", not "what" or "how" when the code is complex
- Use `self::assert*` instead of `$this->assert*` in PHPUnit tests
- Commit messages should be short, concise, and written in English
- Each commit message should be a single sentence that conveys the essence of the change
- Do not include Claude mentions in commit messages
- Always prefer single-line commit messages

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