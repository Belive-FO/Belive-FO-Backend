# BeLive FlowOffice - Backend

Laravel 12 backend application for the BeLive FlowOffice management system.

## Requirements

- PHP 8.3 or higher
- Composer
- Node.js 18+ and npm
- PostgreSQL (via Supabase) or SQLite for local development

### Required PHP Extensions

- `mbstring`
- `pdo`
- `pdo_pgsql` (for Supabase/PostgreSQL)
- `pdo_sqlite` (optional, for local development)

## Installation

1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure environment variables**
   
   Edit `.env` and configure:
   - Supabase credentials (see `.env.example` for details)
   - Database connection
   - Cache driver (recommended: `CACHE_STORE=file` for development)

4. **Run setup script**
   ```bash
   composer run setup
   ```
   
   This will:
   - Install Composer dependencies
   - Copy `.env` if it doesn't exist
   - Generate application key
   - Run migrations
   - Install npm dependencies
   - Build assets

## Development

### Start Development Server

```bash
composer run dev
```

This starts:
- Laravel development server
- Queue worker
- Log viewer (Pail)
- Vite dev server

### Available Commands

```bash
# Testing
composer run test              # Run all tests
php artisan test --filter=TestName  # Run specific test

# Code Quality
vendor/bin/pint --dirty       # Format code

# Supabase
php artisan supabase:test      # Test Supabase connection
php artisan supabase:seed      # Seed database using SQL files

# Laravel Boost (AI Development)
php artisan boost:install      # Install/update Boost
php artisan boost:update       # Update Boost resources
php artisan boost:mcp          # Start MCP server
```

## Database Seeding

### Supabase SQL Seeding

The project uses SQL files for seeding Supabase database. This approach is recommended for infrastructure setup and initial data.

**Location:** SQL seed files are stored in `database/seeds/sql/`

**Usage:**
```bash
# Seed all SQL files (executed in alphabetical order)
php artisan supabase:seed

# Seed a specific file
php artisan supabase:seed 001_initial_setup.sql
```

**Safety Features:**
- Automatically blocks DELETE, TRUNCATE, and DROP operations
- Uses transactions for rollback on errors
- Requires `--force` flag for dangerous operations
- Warns in production environment

**File Naming Convention:**
- Use numbered prefixes: `001_description.sql`, `002_description.sql`
- Files are executed in alphabetical order

**Example:**
```bash
# Create your seed file
# database/seeds/sql/001_initial_setup.sql

# Run the seeder
php artisan supabase:seed

# Or seed specific file
php artisan supabase:seed 001_initial_setup.sql
```

> See [docs/SUPABASE_SEEDING.md](docs/SUPABASE_SEEDING.md) for detailed guide and best practices.

## Project Structure

### Modular Monolith Architecture

The application follows a modular monolith pattern:

```
app/Modules/
├── Shared/          # Cross-cutting concerns
│   ├── Contracts/   # Interfaces
│   ├── Events/      # Domain events
│   └── ValueObjects/
├── Attendance/      # Attendance module
├── Leave/           # Leave management module
└── Claims/          # Claims module
```

See [docs/MODULE_STRUCTURE.md](docs/MODULE_STRUCTURE.md) for detailed architecture documentation.

## Configuration

### Supabase

Configure Supabase in `.env`:

```env
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_SECRET=your-service-role-key
SUPABASE_JWT_SECRET=your-jwt-secret
```

Test connection:
```bash
php artisan supabase:test
```

### Laravel Boost

For AI-assisted development with Cursor/Claude Code:

1. Install Boost: `php artisan boost:install`
2. Configure MCP server (see [docs/LARAVEL_BOOST_INSTALLATION.md](docs/LARAVEL_BOOST_INSTALLATION.md))
3. Set `CACHE_STORE=file` in `.env` for development

## Testing

Tests are located in the `tests/` directory:

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with filter
php artisan test --filter=testName
```

See [docs/TEST_PLAN.md](docs/TEST_PLAN.md) for testing guidelines.

## Code Style

This project uses Laravel Pint for code formatting:

```bash
vendor/bin/pint --dirty
```

## Documentation

- [Module Structure](docs/MODULE_STRUCTURE.md) - Architecture details
- [Laravel Boost Installation](docs/LARAVEL_BOOST_INSTALLATION.md) - AI development setup
- [Test Plan](docs/TEST_PLAN.md) - Testing strategy

## Key Packages

### Production

- `laravel/framework` ^12.0
- `saeedvir/supabase` ^1.0 - Supabase integration & JWT generation
- `spatie/laravel-activitylog` ^4.11 - Audit trail logging

#### Not Used (Replaced by Supabase-First Architecture)

- ~~`laravel/sanctum`~~ - Replaced by Supabase JWT (validated by Next.js BFF)
- ~~`spatie/laravel-permission`~~ - Replaced by Supabase RLS policies

> See [Architecture Decisions](docs/ARCHITECTURE-DECISIONS.md) for details on the Supabase-first approach.

### Development

- `laravel/boost` ^2.1 - AI development assistant
- `laravel/pint` ^1.24 - Code formatter
- `phpunit/phpunit` ^11.5.3 - Testing framework

## Troubleshooting

### MCP Server Issues

If Laravel Boost MCP server fails to start:

1. Check PHP extensions: `php -m | grep mbstring`
2. Set `CACHE_STORE=file` in `.env`
3. Clear config cache: `php artisan config:clear`
4. See [Laravel Boost Installation Guide](docs/LARAVEL_BOOST_INSTALLATION.md)

### Database Connection Issues

1. Verify Supabase credentials in `.env`
2. Test connection: `php artisan supabase:test`
3. Check PDO drivers: `php -m | grep pdo`

### Cache Issues

For development, use file cache:
```env
CACHE_STORE=file
```

This avoids database dependencies and PDO driver requirements.

## License

[Your License Here]
