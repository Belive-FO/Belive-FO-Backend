# BeLive FlowOffice - Modular Monolith Structure

This document describes the modular monolith architecture implemented in the Laravel backend.

## Directory Structure

```
app/Modules/
├── Shared/                    # Cross-cutting concerns
│   ├── Contracts/            # Interfaces between modules
│   │   ├── AttendanceServiceInterface.php
│   │   ├── LeaveServiceInterface.php
│   │   └── UserServiceInterface.php
│   ├── Events/               # Domain events (module communication)
│   │   ├── AttendanceClockedIn.php
│   │   ├── LeaveApproved.php
│   │   └── UserCreated.php
│   └── ValueObjects/         # Shared value objects
│       ├── UserId.php
│       ├── DateRange.php
│       └── Money.php
│
├── Attendance/               # Attendance module
│   ├── Domain/
│   │   ├── Models/
│   │   ├── Rules/          # Business rule validators (renamed from Policies/)
│   │   ├── Services/
│   │   │   └── AttendanceService.php
│   │   └── Events/
│   ├── Application/
│   │   ├── UseCases/
│   │   └── DTOs/
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   └── Adapters/
│   ├── Presentation/
│   │   └── Http/Controllers/
│   └── AttendanceServiceProvider.php
│
├── Leave/                    # Leave module
│   ├── Domain/
│   │   ├── Models/
│   │   ├── Rules/          # Business rule validators (renamed from Policies/)
│   │   ├── Services/
│   │   │   └── LeaveService.php
│   │   └── Events/
│   ├── Application/
│   │   ├── UseCases/
│   │   └── DTOs/
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   ├── Adapters/
│   │   └── Listeners/
│   ├── Presentation/
│   │   └── Http/Controllers/
│   └── LeaveServiceProvider.php
│
└── Claims/                   # Claims module
    ├── Domain/
    ├── Application/
    ├── Infrastructure/
    ├── Presentation/
    └── ClaimsServiceProvider.php
```

## Module Communication Rules

### ✅ DO:
- Use interfaces from `Shared/Contracts` for inter-module communication
- Use domain events from `Shared/Events` for async communication
- Keep modules independent and self-contained

### ❌ DON'T:
- Import models from other modules directly
- Access another module's database tables directly
- Create tight coupling between modules

## Service Providers

All module service providers are registered in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Attendance\AttendanceServiceProvider::class,
    App\Modules\Leave\LeaveServiceProvider::class,
    App\Modules\Claims\ClaimsServiceProvider::class,
];
```

## Configuration

- **Supabase**: `config/supabase.php`
- **Modules**: `config/modules.php`
- **BFF**: `config/services.php` (bff.secret)

## Environment Variables

Required Supabase configuration in `.env`:

```env
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_SECRET=your-service-role-key
SUPABASE_JWT_SECRET=your-super-secret-jwt-secret-at-least-32-characters
```

## Next Steps

1. Implement domain models in each module
2. Create use cases (handlers) for business logic
3. Set up repositories for data access
4. Create controllers for API endpoints
5. Configure routes for each module

