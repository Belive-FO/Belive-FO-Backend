# BeLive FlowOffice

> Enterprise FlowOffice Management System - A modular monolith built with Laravel and Supabase

## Overview

BeLive FlowOffice is a comprehensive enterprise management system designed for modern organizations. The system provides modules for attendance tracking, leave management, and claims processing, built using a modular monolith architecture.

## Architecture

This project follows a **modular monolith** architecture pattern, allowing for clean separation of concerns while maintaining a single deployable application. Each module is self-contained with its own domain logic, but shares common infrastructure.

### Tech Stack

- **Backend**: Laravel 12 (PHP 8.3+)
- **Database**: Supabase (PostgreSQL)
- **Authentication**: Laravel Sanctum
- **Authorization**: Spatie Laravel Permission
- **AI Development**: Laravel Boost (for Cursor/Claude Code)
- **Frontend**: Vite + Tailwind CSS

## Project Structure

```
Belive-FO/
├── backend/              # Laravel backend application
│   ├── app/             # Application code
│   │   └── Modules/    # Modular monolith modules
│   ├── config/         # Configuration files
│   ├── database/       # Migrations and seeders
│   ├── docs/           # Documentation
│   └── tests/          # Test suite
├── Backend-System-Architecture.md
└── Belive-FO-Implementation-Plan.md
```

## Getting Started

### Prerequisites

- PHP 8.3 or higher
- Composer
- Node.js and npm
- Supabase account and project

### Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Belive-FO
   ```

2. **Set up the backend**
   ```bash
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure environment variables**
   - Copy `.env.example` to `.env`
   - Configure Supabase credentials (see `backend/.env.example`)
   - Set up database connection

4. **Install dependencies and run**
   ```bash
   composer run setup  # Installs dependencies and builds assets
   composer run dev    # Starts development server
   ```

## Documentation

- [Backend Module Structure](backend/docs/MODULE_STRUCTURE.md) - Architecture and module organization
- [Laravel Boost Installation](backend/docs/LARAVEL_BOOST_INSTALLATION.md) - AI development setup
- [Test Plan](backend/docs/TEST_PLAN.md) - Testing strategy and guidelines
- [Implementation Plan](Belive-FO-Implementation-Plan.md) - Complete implementation guide
- [System Architecture](Backend-System-Architecture.md) - System design and architecture

## Development

### Backend Development

See [backend/README.md](backend/README.md) for detailed backend setup instructions.

### Available Commands

```bash
# Backend
cd backend
composer run dev      # Start development server
composer run test     # Run tests
php artisan supabase:test  # Test Supabase connection
```

## Modules

The application is organized into the following modules:

- **Attendance** - Clock in/out, attendance tracking
- **Leave** - Leave requests and approvals
- **Claims** - Expense claims and reimbursements

Each module follows Domain-Driven Design principles with clear boundaries.

## Contributing

1. Follow the modular monolith architecture guidelines
2. Write tests for new features
3. Follow Laravel coding standards (Pint)
4. Update documentation as needed

## License

[Your License Here]

## Support

For questions or issues, please refer to the documentation in the `backend/docs/` directory.

