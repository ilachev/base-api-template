#!/bin/bash

# Skip CS Fixer check in CI to avoid PHP version compatibility issues
echo "Running modified linting for CI environment"
echo "Static analysis only (skipping CS Fixer)"
php vendor/bin/phpstan analyse