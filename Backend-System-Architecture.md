# BeLive FlowOffice - TRUE Modular Monolith Implementation

> **Critical Fix:** This document corrects the implementation to follow actual modular monolith principles.

---

## What Makes It a TRUE Modular Monolith

### Core Principles (Non-Negotiable)

1. **Module Independence**
   - Each module has its own bounded context
   - Modules communicate ONLY through defined interfaces
   - No direct database access across modules

2. **High Cohesion, Low Coupling**
   - Everything related to "Attendance" lives in Attendance module
   - Leave module cannot import Attendance models directly

3. **Extractability**
   - Each module can be extracted to a microservice later
   - Without rewriting business logic

---

## Corrected Directory Structure

```
belive-api/
├── app/
│   ├── Modules/
│   │   ├── Shared/              # Cross-cutting concerns
│   │   │   ├── Contracts/       # Interfaces between modules
│   │   │   │   ├── AttendanceServiceInterface.php
│   │   │   │   ├── LeaveServiceInterface.php
│   │   │   │   └── UserServiceInterface.php
│   │   │   │
│   │   │   ├── Events/          # Domain events (module communication)
│   │   │   │   ├── AttendanceClockedIn.php
│   │   │   │   ├── LeaveApproved.php
│   │   │   │   └── UserCreated.php
│   │   │   │
│   │   │   └── ValueObjects/    # Shared value objects
│   │   │       ├── UserId.php
│   │   │       ├── DateRange.php
│   │   │       └── Money.php
│   │   │
│   │   ├── Attendance/
│   │   │   ├── Domain/
│   │   │   │   ├── Models/
│   │   │   │   │   └── Attendance.php        # Attendance Eloquent model
│   │   │   │   ├── Policies/
│   │   │   │   │   └── AttendancePolicy.php
│   │   │   │   ├── Services/
│   │   │   │   │   └── AttendanceService.php
│   │   │   │   └── Events/
│   │   │   │       ├── AttendanceClockedIn.php
│   │   │   │       └── AttendanceClockedOut.php
│   │   │   │
│   │   │   ├── Application/
│   │   │   │   ├── UseCases/
│   │   │   │   │   ├── ClockIn/
│   │   │   │   │   │   ├── ClockInCommand.php
│   │   │   │   │   │   └── ClockInHandler.php
│   │   │   │   │   └── ClockOut/
│   │   │   │   │       ├── ClockOutCommand.php
│   │   │   │   │       └── ClockOutHandler.php
│   │   │   │   └── DTOs/
│   │   │   │       └── AttendanceDTO.php
│   │   │   │
│   │   │   ├── Infrastructure/
│   │   │   │   ├── Persistence/
│   │   │   │   │   └── AttendanceRepository.php
│   │   │   │   └── Adapters/
│   │   │   │       └── SupabaseAdapter.php
│   │   │   │
│   │   │   ├── Presentation/
│   │   │   │   └── Http/
│   │   │   │       └── Controllers/
│   │   │   │           └── AttendanceController.php
│   │   │   │
│   │   │   └── AttendanceServiceProvider.php  # Module registration
│   │   │
│   │   ├── Leave/
│   │   │   ├── Domain/
│   │   │   │   ├── Models/
│   │   │   │   │   └── Leave.php              # Leave Eloquent model
│   │   │   │   ├── Policies/
│   │   │   │   │   └── LeavePolicy.php
│   │   │   │   ├── Services/
│   │   │   │   │   ├── LeaveService.php
│   │   │   │   │   └── LeaveBalanceService.php
│   │   │   │   └── Events/
│   │   │   │       ├── LeaveSubmitted.php
│   │   │   │       └── LeaveApproved.php
│   │   │   │
│   │   │   ├── Application/
│   │   │   │   ├── UseCases/
│   │   │   │   │   ├── SubmitLeave/
│   │   │   │   │   │   ├── SubmitLeaveCommand.php
│   │   │   │   │   │   └── SubmitLeaveHandler.php
│   │   │   │   │   └── ApproveLeave/
│   │   │   │   │       ├── ApproveLeaveCommand.php
│   │   │   │   │       └── ApproveLeaveHandler.php
│   │   │   │   └── DTOs/
│   │   │   │       └── LeaveDTO.php
│   │   │   │
│   │   │   ├── Infrastructure/
│   │   │   │   ├── Persistence/
│   │   │   │   │   └── LeaveRepository.php
│   │   │   │   ├── Adapters/
│   │   │   │   │   └── LarkApprovalAdapter.php
│   │   │   │   └── Listeners/
│   │   │   │       └── OnAttendanceClockedIn.php  # Listens to Attendance events
│   │   │   │
│   │   │   ├── Presentation/
│   │   │   │   └── Http/
│   │   │   │       └── Controllers/
│   │   │   │           └── LeaveController.php
│   │   │   │
│   │   │   └── LeaveServiceProvider.php
│   │   │
│   │   └── Claims/
│   │       └── [Similar structure]
│   │
│   └── Providers/
│       └── ModuleServiceProvider.php   # Registers all modules
│
└── config/
    └── modules.php                     # Module configuration
```

---

## Critical Rules for Module Communication

### ❌ NEVER DO THIS

```php
// ❌ WRONG - Leave module directly accessing Attendance model
namespace App\Modules\Leave\Application;

use App\Modules\Attendance\Domain\Models\Attendance;  // ❌ FORBIDDEN!

class LeaveService 
{
    public function canTakeLeave($userId) 
    {
        // ❌ Direct query to another module's database
        $attendance = Attendance::where('user_id', $userId)->get();
        return count($attendance) > 100;
    }
}
```

**Why it's wrong:**
- Leave module now depends on Attendance's internal implementation
- Can't extract Leave to microservice without bringing Attendance along
- Database schema changes in Attendance break Leave module

---

### ✅ CORRECT - Use Events

```php
// ✅ CORRECT - Attendance publishes event
namespace App\Modules\Attendance\Domain\Services;

use App\Modules\Shared\Events\AttendanceClockedIn;

class AttendanceService 
{
    public function clockIn($userId, $latitude, $longitude) 
    {
        $attendance = Attendance::create([...]);
        
        // Publish domain event
        event(new AttendanceClockedIn(
            userId: $userId,
            attendanceId: $attendance->id,
            clockedAt: $attendance->clocked_at
        ));
        
        return $attendance;
    }
}

// ✅ CORRECT - Leave listens to event
namespace App\Modules\Leave\Infrastructure\Listeners;

use App\Modules\Shared\Events\AttendanceClockedIn;

class OnAttendanceClockedIn 
{
    public function handle(AttendanceClockedIn $event) 
    {
        // Leave module can react without knowing Attendance internals
        Log::info("User {$event->userId} clocked in at {$event->clockedAt}");
        
        // Could update leave eligibility based on attendance patterns
        // But doesn't directly access Attendance database!
    }
}
```

---

### ✅ CORRECT - Use Service Interface

```php
// Define contract in Shared module
namespace App\Modules\Shared\Contracts;

interface AttendanceServiceInterface 
{
    public function getUserAttendanceCount(int $userId, DateRange $period): int;
    public function isUserClockedIn(int $userId): bool;
}

// Attendance implements it
namespace App\Modules\Attendance\Domain\Services;

use App\Modules\Shared\Contracts\AttendanceServiceInterface;

class AttendanceService implements AttendanceServiceInterface 
{
    public function getUserAttendanceCount(int $userId, DateRange $period): int 
    {
        return Attendance::where('user_id', $userId)
            ->whereBetween('clocked_at', [$period->start, $period->end])
            ->count();
    }
    
    public function isUserClockedIn(int $userId): bool 
    {
        return Attendance::where('user_id', $userId)
            ->whereNull('clocked_out_at')
            ->exists();
    }
}

// Leave uses the interface (NOT the concrete class)
namespace App\Modules\Leave\Application\UseCases\SubmitLeave;

use App\Modules\Shared\Contracts\AttendanceServiceInterface;

class SubmitLeaveHandler 
{
    public function __construct(
        private AttendanceServiceInterface $attendanceService  // ✅ Depends on interface
    ) {}
    
    public function handle(SubmitLeaveCommand $command) 
    {
        // ✅ Call through interface
        $attendanceCount = $this->attendanceService->getUserAttendanceCount(
            $command->userId,
            new DateRange(now()->startOfYear(), now())
        );
        
        if ($attendanceCount < 100) {
            throw new InsufficientAttendanceException();
        }
        
        // Proceed with leave creation...
    }
}

// Service Provider binds interface to implementation
namespace App\Modules\Attendance;

class AttendanceServiceProvider extends ServiceProvider 
{
    public function register() 
    {
        $this->app->bind(
            AttendanceServiceInterface::class,
            AttendanceService::class
        );
    }
}
```

---

## Database Isolation in Modular Monolith

### Current Implementation (CORRECT for Monolith)

```
Single Database: Supabase Postgres

Tables:
├── attendance          ← Owned by Attendance module
├── leaves              ← Owned by Leave module
├── claims              ← Owned by Claims module
└── users               ← Owned by Shared/User module

Rule: Each module ONLY queries its own tables!
```

### How Modules Query Data

```php
// ❌ WRONG
namespace App\Modules\Leave;

// Direct query to another module's table
$attendance = DB::table('attendance')->where('user_id', $userId)->get();

// ✅ CORRECT - Query through service interface
$attendanceCount = $this->attendanceService->getUserAttendanceCount($userId, $period);
```

---

## Module Service Providers

### AttendanceServiceProvider.php

```php
namespace App\Modules\Attendance;

use Illuminate\Support\ServiceProvider;
use App\Modules\Shared\Contracts\AttendanceServiceInterface;
use App\Modules\Attendance\Domain\Services\AttendanceService;

class AttendanceServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Bind interface to implementation
        $this->app->bind(
            AttendanceServiceInterface::class,
            AttendanceService::class
        );
    }
    
    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/Presentation/Http/routes.php');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Persistence/Migrations');
        
        // Register event listeners
        Event::listen(
            \App\Modules\Shared\Events\AttendanceClockedIn::class,
            \App\Modules\Attendance\Infrastructure\Listeners\UpdateAttendanceStats::class
        );
    }
}
```

### LeaveServiceProvider.php

```php
namespace App\Modules\Leave;

use Illuminate\Support\ServiceProvider;
use App\Modules\Shared\Contracts\LeaveServiceInterface;
use App\Modules\Leave\Domain\Services\LeaveService;

class LeaveServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            LeaveServiceInterface::class,
            LeaveService::class
        );
    }
    
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/Presentation/Http/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Persistence/Migrations');
        
        // Leave module listens to Attendance events
        Event::listen(
            \App\Modules\Shared\Events\AttendanceClockedIn::class,
            \App\Modules\Leave\Infrastructure\Listeners\OnAttendanceClockedIn::class
        );
    }
}
```

### Register All Modules

```php
// config/app.php
'providers' => [
    // ...
    App\Modules\Attendance\AttendanceServiceProvider::class,
    App\Modules\Leave\LeaveServiceProvider::class,
    App\Modules\Claims\ClaimsServiceProvider::class,
];
```

---

## Inter-Module Communication Patterns

### Pattern 1: Domain Events (Async)

**Use when:** Module A doesn't need immediate response from Module B

```php
// Attendance publishes
event(new AttendanceClockedIn($userId, $attendanceId));

// Leave reacts (asynchronously)
class OnAttendanceClockedIn {
    public function handle(AttendanceClockedIn $event) {
        // Update leave eligibility cache
    }
}
```

### Pattern 2: Service Interface (Sync)

**Use when:** Module A needs data from Module B immediately

```php
// Leave needs to check attendance
$count = $this->attendanceService->getUserAttendanceCount($userId, $period);

if ($count < 100) {
    throw new InsufficientAttendanceException();
}
```

### Pattern 3: Shared Read Models (Query Side)

**Use when:** Multiple modules need to display same data

```php
// Create a read model in Shared module
namespace App\Modules\Shared\ReadModels;

class EmployeeOverview 
{
    public static function getOverview(int $userId): array 
    {
        return [
            'attendance' => app(AttendanceServiceInterface::class)->getStats($userId),
            'leave' => app(LeaveServiceInterface::class)->getBalance($userId),
            'claims' => app(ClaimsServiceInterface::class)->getPendingAmount($userId),
        ];
    }
}
```

---

## Testing Module Independence

### Test 1: Can Module Run Standalone?

```php
// Test if Leave module can function without Attendance
class LeaveModuleIndependenceTest extends TestCase 
{
    public function test_leave_module_works_without_attendance() 
    {
        // Mock the attendance service interface
        $this->mock(AttendanceServiceInterface::class, function ($mock) {
            $mock->shouldReceive('getUserAttendanceCount')
                ->andReturn(150);
        });
        
        // Leave module should work fine with mocked dependency
        $response = $this->postJson('/api/leave/submit', [
            'leave_type' => 'annual',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-05',
        ]);
        
        $response->assertOk();
    }
}
```

### Test 2: No Direct Model Access

```php
// Static analysis check
// Run: ./vendor/bin/phpstan analyze

// phpstan.neon
rules:
    - 'App\Modules\Leave cannot depend on App\Modules\Attendance\Domain\Models'
    - 'App\Modules\Claims cannot depend on App\Modules\Leave\Domain\Models'
```

---

## Migration Path to Microservices

If you later decide to extract Leave module to its own microservice:

### Step 1: Module Already Has Clear Boundaries ✅

```
Leave module (self-contained):
├── Domain (business logic)
├── Application (use cases)  
├── Infrastructure (repositories, adapters)
└── Presentation (controllers)
```

### Step 2: Replace Service Interface with HTTP Client

```php
// Before (Monolith):
class SubmitLeaveHandler {
    public function __construct(
        private AttendanceServiceInterface $attendanceService
    ) {}
}

// After (Microservice):
class SubmitLeaveHandler {
    public function __construct(
        private AttendanceHttpClient $attendanceClient  // HTTP instead of interface
    ) {}
}

// Implementation identical:
$count = $this->attendanceClient->getUserAttendanceCount($userId, $period);
```

### Step 3: Replace Events with Message Queue

```php
// Before (Monolith):
event(new AttendanceClockedIn($userId));

// After (Microservice):
RabbitMQ::publish('attendance.clocked_in', ['user_id' => $userId]);
```

**No business logic changes!** Only infrastructure swap.

---

## Verification Checklist

Use this to verify your implementation is truly modular:

```
Module Independence:
☐ Each module has its own namespace
☐ Modules don't import other modules' Models
☐ All inter-module calls go through interfaces
☐ Events are used for async communication

Database Isolation:
☐ Each module only queries its own tables
☐ Cross-module data access uses service interfaces
☐ Repositories are module-specific

Extractability:
☐ Can mock all module dependencies for testing
☐ Domain logic has zero framework dependencies
☐ Each module has its own ServiceProvider

Documentation:
☐ Module contracts documented in Shared/Contracts
☐ Domain events documented in Shared/Events
☐ Each module has README explaining its boundaries
```

---

## Quick Reference: Module Communication

| Scenario | Pattern | Example |
|----------|---------|---------|
| **Leave needs attendance count** | Service Interface (sync) | `$attendanceService->getCount()` |
| **Notify when leave approved** | Domain Event (async) | `event(new LeaveApproved())` |
| **Display employee dashboard** | Shared Read Model | `EmployeeOverview::get()` |
| **Claims need leave balance** | Service Interface (sync) | `$leaveService->getBalance()` |

---

## Common Mistakes to Avoid

### ❌ Mistake 1: Shared Models

```php
// ❌ DON'T create shared Eloquent models
app/Models/
├── User.php        ← Used by all modules (tight coupling!)
├── Attendance.php  
└── Leave.php
```

```php
// ✅ DO - Each module owns its models
app/Modules/
├── Attendance/Domain/Models/Attendance.php
├── Leave/Domain/Models/Leave.php
└── Shared/
    ├── Contracts/UserServiceInterface.php  # Interface only
    └── ValueObjects/UserId.php             # Value object (no DB)
```

### ❌ Mistake 2: Shared Services Layer

```php
// ❌ DON'T
app/Services/
└── AttendanceService.php  ← Used by multiple modules

// ✅ DO
app/Modules/Attendance/Domain/Services/AttendanceService.php
app/Modules/Shared/Contracts/AttendanceServiceInterface.php
```

### ❌ Mistake 3: Cross-Module Transactions

```php
// ❌ DON'T span transaction across modules
DB::transaction(function() {
    Attendance::create([...]);  // Attendance module
    Leave::update([...]);       // Leave module - WRONG!
});

// ✅ DO - Use events for eventual consistency
Attendance::create([...]);
event(new AttendanceClockedIn());  // Leave reacts asynchronously
```

---

**Summary:**

Your implementation IS a modular monolith when:
✅ Modules communicate ONLY through defined interfaces
✅ Each module can be tested independently
✅ No direct cross-module database access
✅ Domain events coordinate modules
✅ Each module can be extracted to microservice without logic rewrite

It is NOT a modular monolith when:
❌ Modules directly import each other's models
❌ Services scattered in app/Services used by all
❌ Cross-module database queries everywhere
❌ Tight coupling between modules

The key is **enforcing boundaries** through interfaces and events!