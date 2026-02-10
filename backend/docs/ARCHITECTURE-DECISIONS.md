# Architecture Decision Records (ADR)

This document records architectural decisions made for the BeLive FlowOffice backend system.

---

## ADR-001: Supabase-First Authentication and Authorization

**Status:** Accepted  
**Date:** 2026-02-06  
**Decision Makers:** Architecture Team

### Context

The system needs to handle:
- User authentication via Lark OAuth
- Row-level access control (users see only their own data, managers see team data)
- Real-time data synchronization
- Audit trail for compliance

We evaluated two approaches:
1. **Laravel-First**: Use Laravel Sanctum for API auth, Spatie Permissions for RBAC, Laravel Policies for authorization
2. **Supabase-First**: Use Supabase JWT for identity, RLS policies for access control, Laravel for business logic only

### Decision

We chose **Supabase-First** architecture.

### Rationale

#### Advantages

1. **Single Source of Truth**: Supabase JWT tokens and RLS policies provide a single, consistent authorization model
2. **Defense in Depth**: RLS policies enforce access control at the database level, impossible to bypass
3. **Reduced Complexity**: Eliminates duplicate authorization logic between Laravel and database
4. **Better Performance**: RLS filtering happens in PostgreSQL, reducing data transfer
5. **Real-time Security**: Supabase Realtime respects RLS policies automatically
6. **Cost Efficiency**: Fewer dependencies, simpler infrastructure

#### Trade-offs

1. **Laravel becomes stateless**: Laravel no longer owns user identity - it trusts the BFF (Next.js)
2. **No Laravel authorization policies**: Complex authorization logic must be expressed as business rules
3. **Tighter coupling with Supabase**: Database choice becomes more significant

### Consequences

#### Eliminated Components

- ❌ **Laravel Sanctum** - Not needed, Supabase JWT handles identity
- ❌ **Spatie Laravel Permission** - Not needed, RLS policies handle access control
- ❌ **Laravel Authorization Policies** (as auth guards) - Replaced by domain rules

#### Retained Components

- ✅ **Spatie Laravel Activity Log** - Still used for audit trail (independent of auth)
- ✅ **Domain Services** - Business logic (geofence validation, leave balance calculation)
- ✅ **Domain Rules** - Business rule validators (renamed from "Policies" to avoid confusion)
- ✅ **Domain Events** - Cross-module coordination
- ✅ **Adapters** - External API integrations

#### Laravel's Authentication Responsibilities

- ✅ **Lark OAuth Validation**: Validates OAuth tokens from Lark
- ✅ **JWT Token Generation**: Generates Supabase-compatible JWT tokens
- ✅ **User Management**: Creates/updates user records after authentication
- ❌ **NOT responsible for**: JWT validation on subsequent requests (Next.js BFF handles this)
- ❌ **NOT responsible for**: Authorization/access control (Supabase RLS handles this)

#### Implementation Pattern

**Request Flow:**
```
1. User authenticates via Lark OAuth → Laravel validates
2. Laravel generates Supabase JWT token
3. Next.js BFF validates Supabase JWT
3. Next.js calls Laravel with:
   - X-User-ID header (from JWT)
   - X-Internal-Key header (shared secret)
4. Laravel TrustedBffMiddleware validates internal key
5. Laravel executes business logic assuming the caller is trusted, and enforces business invariants, not access control.
6. Laravel writes to Supabase DB (service role)
7. Supabase RLS filters data automatically
8. Supabase Realtime broadcasts (respects RLS)
```

**Code Pattern:**
```php
// Before (Laravel Policy - REMOVED)
$this->authorize('clockIn', Attendance::class);

// After (Domain Rule - KEPT)
$validation = $this->attendanceRules->canClockIn($userId, $location);
if ($validation->failed()) {
    throw new BusinessRuleViolationException($validation->errors());
}
```

### Alternatives Considered

1. **Laravel-First**: Rejected due to duplicate authorization logic and split-brain security
2. **Hybrid**: Rejected due to complexity and potential for inconsistencies

### References

- `BACKEND.md` - Detailed explanation of authority boundaries
- `Backend-System-Architecture.md` - Complete architecture documentation
- `Belive-FO-Implementation-Plan.md` - Implementation guide

---

## ADR-002: Domain Rules vs Authorization Policies

**Status:** Accepted  
**Date:** 2026-02-06  
**Decision Makers:** Architecture Team

### Context

With Supabase handling authorization, Laravel's `Domain/Policies/` folders were confusing because:
- "Policy" in Laravel context means authorization policy
- These folders contain business rule validators, not authorization checks

### Decision

Rename `Domain/Policies/` to `Domain/Rules/` in all modules.

### Rationale

1. **Clarity**: "Rules" clearly indicates business rule validation
2. **Avoid Confusion**: No confusion with Laravel authorization policies
3. **Semantic Accuracy**: These classes validate business rules, not authorize access

### Consequences

- All module `Domain/Policies/` folders renamed to `Domain/Rules/`
- Class names changed from `*Policy` to `*Rules` (e.g., `AttendancePolicy` → `AttendanceRules`)
- Documentation updated to reflect new naming

---

## ADR-003: Laravel Handles JWT Token Generation

**Status:** Accepted  
**Date:** 2026-02-10  
**Decision Makers:** Architecture Team

### Context

There was confusion about who generates JWT tokens in our Supabase-first architecture. Some documentation implied Supabase generates tokens, but actually Laravel does.

### Decision

**Laravel is responsible for JWT token generation** after successful Lark OAuth authentication.

### Rationale

1. **Control**: Laravel controls the authentication flow and user data
2. **Security**: Laravel can add custom claims to JWT (roles, permissions)
3. **Flexibility**: Easy to switch authentication providers (Lark → others)
4. **Supabase Auth not needed**: We use Lark for authentication, not Supabase Auth

### Implementation

```php
// After Lark OAuth validation
$supabaseToken = app(SupabaseJwtService::class)->generateToken($user);
```

The JWT is:
- Generated by Laravel using Supabase JWT secret
- Validated by Next.js BFF on subsequent requests
- Used by Supabase RLS for authorization

### Authentication vs Authorization

- **Authentication** (Laravel): Who are you?
  - Validates Lark OAuth
  - Generates JWT token
  
- **Authorization** (Supabase RLS): What can you access?
  - Uses JWT to identify user
  - Filters data based on policies

---

## Future ADRs

As architectural decisions are made, they should be documented here following the same format:
- ADR-004: [Future decision]
- ADR-005: [Future decision]
- ...



