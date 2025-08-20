# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mission Control is a Laravel 12.x web application serving as a utility dashboard for Amtelco call center environments. It provides call center management features, agent analytics, and various integrations for communication systems.

## Common Development Commands

### Setup and Installation
```bash
# Install dependencies
composer install
npm install
npx puppeteer browsers install chrome-headless-shell

# Build frontend assets
npm run build

# Database setup
php artisan migrate
php artisan storage:link
```

### Development Workflow

#### IMPORTANT: Laravel Sail Usage
**Always use Laravel Sail commands** (`./vendor/bin/sail`) instead of direct Docker commands. Sail is the official Laravel Docker development environment and handles all container orchestration properly.

```bash
# Using Laravel Sail (Docker-based development) - PREFERRED METHOD
./vendor/bin/sail up -d                  # Start development environment
./vendor/bin/sail artisan migrate        # Run migrations in container
./vendor/bin/sail artisan horizon        # Start queue workers
./vendor/bin/sail npm run dev           # Start Vite dev server with HMR
./vendor/bin/sail artisan test          # Run test suite
./vendor/bin/sail down                  # Stop all containers

# Container management
./vendor/bin/sail ps                    # List running containers
./vendor/bin/sail logs [service]        # View container logs
./vendor/bin/sail shell                 # Access Laravel container shell
./vendor/bin/sail build --no-cache      # Rebuild containers

# NEVER use direct docker commands like:
# ❌ docker exec mission-control.test ...
# ❌ docker-compose up
# ✅ ./vendor/bin/sail ...

# Without Sail (local PHP)
php artisan serve          # Start development server
npm run dev               # Start Vite dev server
php artisan horizon       # Start queue workers
php artisan test          # Run test suite
```

#### Troubleshooting Sail
If containers fail with "bash\\r: No such file or directory":
1. Fix line endings: `sed -i 's/\r$//' docker/8.4/start-container docker/mysql/*.sh docker/mariadb/*.sh`
2. Rebuild: `./vendor/bin/sail build --no-cache`
3. Start: `./vendor/bin/sail up -d`

### Code Quality
```bash
# Format code using Laravel Pint (PSR-12 standards)
./vendor/bin/sail pint
# or without Sail:
vendor/bin/pint

# Run specific tests
./vendor/bin/sail artisan test --filter=TestClassName
./vendor/bin/sail artisan test tests/Feature/SpecificTest.php
# or without Sail:
php artisan test --filter=TestClassName
php artisan test tests/Feature/SpecificTest.php
```

### Common Artisan Commands
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue management
php artisan queue:work
php artisan horizon
php artisan horizon:terminate

# Database
php artisan migrate:fresh --seed
php artisan db:seed
```

## Architecture Overview

### Core Technologies
- **Backend**: Laravel 12.x with PHP 8.4+
- **Frontend**: Livewire 3.x components with TailwindCSS 4.x
- **Real-time**: Laravel Horizon for queues, WebSocket support
- **Database**: Multi-database support (MySQL, PostgreSQL, SQLite)
- **Authentication**: Laravel Jetstream with Sanctum for APIs, SAML2 support

### Key Architectural Patterns

1. **Multi-tenancy**: Team-based access control via Laravel Jetstream
2. **Queue-driven Architecture**: Heavy use of Jobs for async processing (email, fax, recordings)
3. **Service Integration Pattern**: Centralized DataSource model for managing third-party credentials
4. **Component-based UI**: Livewire components for interactive features
5. **Modular Utilities**: Feature flags for enabling/disabling functionality per team

### Directory Structure

- `app/Livewire/` - Interactive UI components (agent analytics, call logs, etc.)
- `app/Jobs/` - Background jobs for processing (emails, faxes, recordings, etc.)
- `app/Console/Commands/` - Custom Artisan commands for automation
- `app/Models/` - Eloquent models following Laravel conventions
- `app/Utilities/` - Helper classes for specific features
- `resources/views/` - Blade templates organized by feature
- `routes/` - Web, API, and console routes

### Key Integrations

- **Amtelco**: Unofficial integration for call center data
- **Communication**: Twilio, RingCentral, SendGrid, WCTP gateway
- **Fax Services**: Multiple providers with unified interface
- **Payment**: Stripe integration for billing
- **Authentication**: SAML2, OAuth (various providers)
- **Audio**: WhisperCPP for transcription, sox/lame for processing

### Testing Approach

- Feature tests for integration testing
- Unit tests for isolated components
- SQLite in-memory database for fast test execution
- Test files mirror the app structure

### Security Considerations

- API whitelist middleware for external integrations
- Team-based authorization policies
- Encrypted sensitive data in database
- Two-factor authentication support
- CSRF protection on all forms
- Sanctum for API authentication

## Development Guidelines

1. Follow Laravel conventions for file structure and naming
2. Use Livewire for interactive UI components
3. Queue long-running tasks using Jobs
4. Implement authorization using Laravel policies
5. Use form requests for validation
6. Keep controllers thin, move logic to services/actions
7. Use Laravel's built-in helpers and facades
8. Strict typing recommended: `declare(strict_types=1);`

## System Requirements

Required system libraries for audio/fax processing:
- sox
- lame
- libsox-fmt-mp3

## Database Considerations

- Uses Laravel migrations for schema management
- Soft deletes on most models
- Team scoping on relevant models
- Encrypted fields for sensitive data (API keys, passwords)