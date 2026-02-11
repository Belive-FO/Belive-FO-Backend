# BeLive FlowOffice - Development Guide

This document combines testing strategy and database seeding guides.

---

## Table of Contents

1. [Testing Strategy](#testing-strategy)
2. [Database Seeding](#database-seeding)

---

# Testing Strategy

Nice work — getting a **modular monolith + Supabase wired and tested** is already a big milestone 👏
At this stage, you don't want to test *features* yet — you want to test **architecture guarantees**. Think: *"Will this still be sane 6 months from now?"*

Below is a **progressive test checklist**, ordered from *foundational* → *business-critical*.

---

## 1️⃣ Infrastructure & Boundary Tests (Highest Priority)

These tests make sure your modular monolith **stays modular**.

### ✅ Module bootstrapping

Test that **each module can boot independently**.

**What to test**

* ServiceProvider loads without other modules enabled
* Contracts are bound correctly
* Events & listeners register properly

**How**

```php
public function test_attendance_module_boots()
{
    $this->assertTrue(
        app()->providerIsLoaded(
            App\Modules\Attendance\AttendanceServiceProvider::class
        )
    );
}
```

💡 If this fails later, you've accidentally introduced coupling.

---

### ✅ Contract enforcement (anti-leak test)

Ensure modules **only communicate via Shared\Contracts**.

**What to test**

* Attendance module resolves `LeaveServiceInterface`
* Not `LeaveService` concrete

```php
public function test_attendance_depends_on_leave_contract_only()
{
    $service = app(\App\Modules\Shared\Contracts\LeaveServiceInterface::class);

    $this->assertNotInstanceOf(
        \App\Modules\Leave\Services\LeaveService::class,
        $service
    );
}
```

🛑 This prevents "just import the class" shortcuts.

---

## 2️⃣ Supabase Integration Tests (Critical)

You tested *connection*. Now test **behavior**.

### ✅ Database connectivity & isolation

Test that:

* Supabase is reachable
* Queries execute
* Schema access is correct

```php
public function test_supabase_can_query_health_check()
{
    $result = DB::connection('supabase')->select('select 1 as ok');

    $this->assertEquals(1, $result[0]->ok);
}
```

---

### ✅ Authorization Tests (Laravel Policies)

Test that Policies correctly enforce authorization:

**What to test**

* Authenticated vs unauthenticated access
* Role-based authorization
* Permission-based authorization

```php
public function test_policy_blocks_unauthorized_access()
{
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    $attendance = Attendance::factory()->create(['user_id' => $otherUser->id]);
    
    $this->actingAs($user);
    
    $response = $this->getJson("/api/attendance/{$attendance->id}");
    
    $response->assertForbidden(); // Policy should block access
}

public function test_policy_allows_authorized_access()
{
    $user = User::factory()->create();
    $user->givePermissionTo('attendance.view-own');
    
    $attendance = Attendance::factory()->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    $response = $this->getJson("/api/attendance/{$attendance->id}");
    
    $response->assertOk(); // Policy should allow access
}
```

If these tests don't work correctly → 🚨 security hole.

---

## 3️⃣ Domain Invariant Tests (Pure Gold)

These are **pure PHP tests** — no DB, no framework.

### ✅ Value Objects

Test immutability & invariants.

```php
public function test_date_range_cannot_be_invalid()
{
    $this->expectException(\InvalidArgumentException::class);

    new DateRange(
        Carbon::now(),
        Carbon::now()->subDay()
    );
}
```

If these tests are solid, refactors become fearless.

---

### ✅ Domain Services

Test **business rules**, not persistence.

Example:

* Cannot clock in twice
* Leave cannot overlap approved leave

```php
public function test_cannot_clock_in_twice_same_day()
{
    $service = new AttendanceService(/* mocked repo */);

    $service->clockIn($userId);
    
    $this->expectException(DomainException::class);
    $service->clockIn($userId);
}
```

---

## 4️⃣ Event-Driven Communication Tests

Your architecture *depends* on events — test them.

### ✅ Domain event dispatch

```php
Event::fake();

$attendanceService->clockIn($userId);

Event::assertDispatched(AttendanceClockedIn::class);
```

---

### ✅ Listener reaction

Ensure listeners:

* React correctly
* Don't break if module is disabled

```php
Event::fake([LeaveApproved::class]);

event(new LeaveApproved($leaveId));

Event::assertListening(
    LeaveApproved::class,
    SyncAttendanceBalance::class
);
```

💡 This ensures async decoupling actually works.

---

## 5️⃣ Repository & Persistence Tests

Now test **Infrastructure layer only**.

### ✅ Repository contract compliance

Test that repositories:

* Implement interfaces
* Don't leak ORM models outside

```php
public function test_repository_returns_domain_entity()
{
    $repo = app(AttendanceRepositoryInterface::class);

    $attendance = $repo->findById($id);

    $this->assertInstanceOf(
        Attendance::class,
        $attendance
    );
}
```

---

## 6️⃣ Application Use Case Tests (Business Flows)

These are **"vertical slice" tests**.

### Example: Clock-in flow

* Valid user
* Policy allows
* Event emitted
* State persisted

```php
public function test_user_can_clock_in()
{
    $useCase = app(ClockInUseCase::class);

    $result = $useCase->execute($userId);

    $this->assertTrue($result->success);
}
```

If this breaks → business logic is broken, not UI.

---

## 7️⃣ HTTP & Auth Boundary Tests

Only after the core is solid.

### ✅ Controller isolation

Controllers should:

* Call use cases
* Never contain business logic

```php
public function test_clock_in_endpoint()
{
    $this->actingAs($user)
         ->postJson('/api/attendance/clock-in')
         ->assertStatus(200);
}
```

---

### ✅ Sanctum Authentication Tests

Test that Sanctum correctly validates session-based authentication:

- Session cookie validation
- CSRF protection
- Unauthenticated requests are rejected

```php
public function test_sanctum_blocks_unauthenticated_requests()
{
    $response = $this->postJson('/api/attendance/clock-in', [
        'latitude' => 3.1390,
        'longitude' => 101.6869,
    ]);

    $response->assertUnauthorized(); // Sanctum should block
}

public function test_sanctum_allows_authenticated_requests()
{
    $user = User::factory()->create();
    $user->givePermissionTo('attendance.create');
    
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/attendance/clock-in', [
            'latitude' => 3.1390,
            'longitude' => 101.6869,
        ]);

    $response->assertOk(); // Sanctum should allow
}

public function test_sanctum_csrf_protection()
{
    // In SPA mode, CSRF cookie must be set first
    $this->get('/sanctum/csrf-cookie');
    
    $user = User::factory()->create();
    
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/attendance/clock-in', [
            'latitude' => 3.1390,
            'longitude' => 101.6869,
        ]);

    $response->assertOk();
}
```

**Note:** Sanctum SPA mode uses session cookies. The session is created after successful Lark OAuth authentication.

---

## 8️⃣ Architectural Regression Tests (Optional but 🔥)

Add **meta tests** to protect structure.

### ❌ No cross-module imports

Use tools like:

* PHPStan
* Deptrac

Example Deptrac rule:

```yaml
ruleset:
  Attendance:
    - Shared
```

If someone imports `Leave\Models\Leave` → build fails 😌

---

## 9️⃣ Spatie Permission Tests

Test that roles and permissions work correctly:

### ✅ Role Assignment

```php
public function test_user_can_have_role()
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'manager']);
    
    $user->assignRole('manager');
    
    $this->assertTrue($user->hasRole('manager'));
}
```

### ✅ Permission Assignment

```php
public function test_user_can_have_permission()
{
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'attendance.create']);
    
    $user->givePermissionTo('attendance.create');
    
    $this->assertTrue($user->hasPermissionTo('attendance.create'));
}
```

### ✅ Role-Based Permissions

```php
public function test_role_grants_permissions()
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'manager']);
    $permission = Permission::create(['name' => 'leave.approve']);
    
    $role->givePermissionTo('leave.approve');
    $user->assignRole('manager');
    
    $this->assertTrue($user->hasPermissionTo('leave.approve'));
}
```

---

## TL;DR – What You Should Test Next (Priority Order)

1. ✅ Module boot & contract binding
2. 🔐 Laravel Policies & authorization
3. 🔑 Sanctum authentication (session validation)
4. 👥 Spatie roles & permissions
5. 🧠 Domain invariants & services
6. 📣 Event dispatch & listeners
7. 🗄 Repository boundaries
8. 🔄 Application use cases
9. 🌐 Controllers & auth
10. 🧱 Architecture regression rules

---

# Database Seeding

## Supabase SQL Seeding Guide

This guide explains how to seed the Supabase database using SQL files in a safe, automated way.

## Overview

The project uses SQL files for seeding Supabase database infrastructure and initial data. This approach is recommended because:

- ✅ Version controlled SQL files
- ✅ Automated execution via Artisan command
- ✅ Built-in safety checks to prevent data loss
- ✅ Direct access to Supabase PostgreSQL for table setup and data seeding

**Note:** User authentication is handled by Laravel Sanctum, not Supabase Auth. This guide covers seeding application data only, not auth users or RLS policies.

## Quick Start

### 1. Create SQL Seed File

Create a file in `database/seeds/sql/` following the naming convention:

```bash
# Example: database/seeds/sql/001_initial_setup.sql
```

### 2. Write Your SQL

```sql
-- Example: Create initial test data
INSERT INTO public.users (id, email, name, role, created_at, updated_at)
VALUES (
    gen_random_uuid(),
    'admin@belive.com',
    'Admin User',
    'super_admin',
    NOW(),
    NOW()
)
ON CONFLICT (email) DO NOTHING;
```

### 3. Run the Seeder

```bash
# Seed all SQL files
php artisan supabase:seed

# Seed specific file
php artisan supabase:seed 001_initial_setup.sql
```

## File Structure

```
backend/
├── database/
│   └── seeds/
│       └── sql/
│           ├── .gitkeep
│           ├── 001_initial_setup.sql.example
│           ├── 001_initial_setup.sql
│           ├── 002_storage_buckets.sql
│           └── 003_test_data.sql
```

## Naming Convention

- Use numbered prefixes: `001_description.sql`, `002_description.sql`
- Files are executed in alphabetical order
- Use descriptive names: `001_initial_setup.sql`, `002_storage_buckets.sql`

## Safety Features

### Automatic Protection

The `supabase:seed` command automatically:

1. **Blocks Dangerous Operations:**
   - `DELETE` statements
   - `TRUNCATE` statements
   - `DROP` statements (DROP TABLE, DROP SCHEMA, etc.)
   - `ALTER TABLE ... DROP` operations

2. **Transaction Safety:**
   - All statements execute within a transaction
   - Automatic rollback on any error
   - No partial changes if something fails

3. **Environment Warnings:**
   - Warns if running in production
   - Requires confirmation before proceeding

### Force Mode

If you absolutely need to execute dangerous operations (not recommended):

```bash
php artisan supabase:seed 001_cleanup.sql --force
```

⚠️ **Warning:** Use `--force` only if you are absolutely certain. The command will still ask for confirmation.

## Best Practices

### ✅ Safe Operations

These operations are safe and recommended:

- **INSERT** statements (adding new data)
- **UPDATE** statements (with explicit WHERE clauses)
- **CREATE** statements (tables, functions, triggers, policies)
- **ALTER TABLE ... ADD** (adding columns, constraints)

### ❌ Dangerous Operations (Blocked)

These operations are blocked by default:

- **DELETE** statements (especially without WHERE)
- **TRUNCATE** statements
- **DROP** statements
- **ALTER TABLE ... DROP** operations

### Idempotent Inserts

Use `ON CONFLICT` to make inserts idempotent:

```sql
INSERT INTO public.users (id, email, name)
VALUES (gen_random_uuid(), 'user@example.com', 'User Name')
ON CONFLICT (email) DO NOTHING;
```

### User Management

**Note:** User authentication is handled by Laravel Sanctum. Users are created through Laravel migrations and seeders, not SQL files. SQL seeding is for application data only.

### Timestamps

Always include `created_at` and `updated_at`:

```sql
INSERT INTO public.users (id, email, created_at, updated_at)
VALUES (
    gen_random_uuid(),
    'user@example.com',
    NOW(),
    NOW()
);
```

## Usage Examples

### Example 1: Initial Setup

```sql
-- database/seeds/sql/001_initial_setup.sql

-- Create initial admin user (Laravel handles authentication)
INSERT INTO public.users (id, email, name, role, created_at, updated_at)
VALUES (
    gen_random_uuid(),
    'admin@belive.com',
    'Super Admin',
    'super_admin',
    NOW(),
    NOW()
)
ON CONFLICT (email) DO NOTHING;
```

### Example 2: Storage Buckets

```sql
-- database/seeds/sql/002_storage_buckets.sql

-- Create storage buckets for file uploads
-- Note: This requires Supabase Storage API or dashboard setup
-- SQL seeding is for database tables only

-- Example: Create receipts bucket (if using Supabase Storage API)
-- INSERT INTO storage.buckets (id, name, public)
-- VALUES ('receipts', 'receipts', false)
-- ON CONFLICT (id) DO NOTHING;
```

### Example 3: Test Data

```sql
-- database/seeds/sql/003_test_data.sql

-- Insert test users (only in development)
DO $$
BEGIN
    IF current_setting('app.env') = 'local' THEN
        INSERT INTO public.users (id, email, name, role, created_at, updated_at)
        VALUES
            (gen_random_uuid(), 'test1@example.com', 'Test User 1', 'employee', NOW(), NOW()),
            (gen_random_uuid(), 'test2@example.com', 'Test User 2', 'manager', NOW(), NOW())
        ON CONFLICT (email) DO NOTHING;
    END IF;
END $$;
```

## Troubleshooting

### Error: "SQL file contains DELETE/TRUNCATE/DROP operations"

**Cause:** Your SQL file contains dangerous operations.

**Solution:**
1. Review your SQL file
2. Remove DELETE/TRUNCATE/DROP statements
3. If you absolutely need them, use `--force` flag (not recommended)

### Error: "Transaction rolled back"

**Cause:** An error occurred during execution.

**Solution:**
1. Check the error message for details
2. Fix the SQL syntax or data issues
3. Re-run the seeder

### Error: "No SQL files found"

**Cause:** No `.sql` files in `database/seeds/sql/` directory.

**Solution:**
1. Create SQL files in `database/seeds/sql/`
2. Ensure files have `.sql` extension
3. Check file permissions

### Error: "Connection refused" or Database errors

**Cause:** Database connection issues.

**Solution:**
1. Verify Supabase credentials in `.env`
2. Test connection: `php artisan supabase:test`
3. Ensure database is accessible

## Integration with Setup

The seeding command is automatically included in the setup script:

```bash
composer run setup
```

This will:
1. Install dependencies
2. Setup environment
3. Run migrations
4. **Run SQL seeders** ← Automatically executed
5. Install npm packages
6. Build assets

## Command Reference

```bash
# Seed all SQL files (alphabetical order)
php artisan supabase:seed

# Seed specific file
php artisan supabase:seed 001_initial_setup.sql

# Force execution (bypasses safety checks)
php artisan supabase:seed 001_cleanup.sql --force

# Help
php artisan supabase:seed --help
```

## Security Notes

⚠️ **Important Security Considerations:**

1. **Never commit passwords** in SQL files
2. **Use environment variables** for sensitive data when possible
3. **Review SQL files** before committing to version control
4. **Test in development** before running in production
5. **Backup database** before running seeders in production

## AI Development Rules

When working with SQL seeders, AI agents must follow these rules (see `.cursorrules`):

- ❌ **NEVER** execute DELETE, TRUNCATE, or DROP statements
- ❌ **NEVER** create or modify RLS policies (Laravel Policies handle authorization)
- ❌ **NEVER** create or modify `auth.users` (Laravel handles authentication)
- ✅ **ONLY** use INSERT, UPDATE (with WHERE), CREATE statements for application data
- ✅ **ALWAYS** use transactions for data modifications
- ✅ **ALWAYS** verify SQL files before executing

