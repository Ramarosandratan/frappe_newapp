# Frappe NewApp Information

## Summary
A Symfony-based web application that integrates with ERPNext for employee management, payroll processing, and salary slip generation. The application provides interfaces for employee data management, salary generation, and statistical reporting.

## Structure
- **src/**: Core application code (Controllers, Services, Entities)
- **templates/**: Twig templates for the UI
- **config/**: Symfony configuration files
- **public/**: Web entry point and public assets
- **assets/**: JavaScript and CSS source files
- **tests/**: PHPUnit test files
- **migrations/**: Database migration files
- **translations/**: Internationalization files

## Language & Runtime
**Language**: PHP
**Version**: 8.2+
**Framework**: Symfony 7.3.*
**Build System**: Composer
**Package Manager**: Composer (PHP), npm (JavaScript)

## Dependencies
**Main Dependencies**:
- symfony/framework-bundle: 7.3.*
- doctrine/orm: ^3.5
- doctrine/doctrine-bundle: ^2.15
- knplabs/knp-snappy-bundle: ^1.10
- league/csv: ^9.24
- symfony/security-bundle: 7.3.*
- symfony/twig-bundle: 7.3.*
- symfony/http-client: 7.3.*

**Development Dependencies**:
- phpunit/phpunit: ^12.2
- symfony/maker-bundle: ^1.0
- symfony/web-profiler-bundle: 7.3.*
- @symfony/webpack-encore: ^1.7.0
- stimulus: ^2.0.0

## Build & Installation
```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install

# Build assets
npm run build

# Database setup
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## Docker
**Configuration**: Docker Compose setup for PostgreSQL database
```yaml
services:
  database:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: app
      POSTGRES_PASSWORD: !ChangeMe!
      POSTGRES_USER: app
    volumes:
      - database_data:/var/lib/postgresql/data:rw
```

## ERPNext Integration
The application integrates with ERPNext through the `ErpNextService` class for:
- User authentication and management
- Employee data synchronization
- Salary structure and payslip operations

### Document Status Management
The newly implemented `ErpNextImportService` handles:
- Document dependency management (Company → Employee → Salary Components → Structure → Slips)
- Automatic document submission for visibility in ERPNext
- Proper state transitions from Draft to Submitted for critical documents
- Batch operations for improved performance

### Import Workflow
1. Company verification/creation
2. Employee creation with proper status
3. Salary component creation with abbreviations
4. Salary structure creation and submission
5. Structure assignment to employees with proper base amount
6. Salary slip creation with earnings components and submission

### Critical Fixes for Salary Processing
- **Salary Structure Assignment**: Now properly sets and validates the base amount
- **Salary Slip Creation**: Ensures all earnings components are correctly included
- **Currency Handling**: Explicitly sets currency for salary slips
- **Amount Validation**: Verifies and updates amounts when documents already exist
- **Submission Status**: Ensures all documents are properly submitted for visibility

## Testing
**Framework**: PHPUnit 12.2
**Test Location**: tests/
**Configuration**: phpunit.dist.xml
**Run Command**:
```bash
php bin/phpunit
```