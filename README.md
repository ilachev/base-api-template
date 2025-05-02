# Base API Template

[![CI](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml/badge.svg)](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml)

A modern PHP API template with Protocol Buffers for schema definition, automatic route generation, and comprehensive testing. Built to run on RoadRunner for high performance.

## Features

- Protocol Buffer based API definitions
- Automatic route generation from proto files
- OpenAPI documentation generation
- Clean architecture with separation of concerns
- RoadRunner-powered for high performance and resource efficiency
- High test coverage and static analysis
- GitHub Actions CI/CD pipeline

## Setup

```bash
# Install dependencies
composer install

# Generate proto artifacts
task proto:gen:all

# Run tests
task test

# Run all verifications (lint, static analysis, tests)
task verify
```

## Running the Application

The application uses RoadRunner for high performance. To run:

```bash
# Install RoadRunner globally (if not already installed)
# Visit https://roadrunner.dev/download for installation instructions

# Start all services and run the application (PostgreSQL + RoadRunner)
task run

# The API will be available at http://localhost:8080

# To stop all services
task services:stop
```

## GitHub Actions

This project uses GitHub Actions for CI/CD:

- **CI Workflow**: Runs on every push and pull request to master
  - Validates code style
  - Runs static analysis
  - Executes all tests
  - Generates proto artifacts

- **Release Workflow**: Runs when a new release is created
  - Builds a production package
  - Attaches the package to the GitHub release
  - Uploads the OpenAPI documentation

## Conventions

### DTO
All DTOs are final readonly classes with public promoted properties:

```php
final readonly class Session
{
    public function __construct(
        public string $id,
        public string $token
    ) {}
}
```
