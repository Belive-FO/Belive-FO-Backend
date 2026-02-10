# BeLive FlowOffice - Documentation Index

## Reading Order for New Developers / AI Agents

Follow this sequence to understand the system architecture:

1. **[ARCHITECTURE-DECISIONS.md](ARCHITECTURE-DECISIONS.md)** - Why we made key architectural choices
2. **[Backend-System-Architecture.md](Backend-System-Architecture.md)** - System design and boundaries
3. **[TRUSTED_BFF_MIDDLEWARE.md](TRUSTED_BFF_MIDDLEWARE.md)** - Security model
4. **[MODULE_STRUCTURE.md](MODULE_STRUCTURE.md)** - Code organization
5. **[Belive-FO-Implementation-Plan.md](Belive-FO-Implementation-Plan.md)** - Implementation roadmap
6. **[TEST_PLAN.md](TEST_PLAN.md)** - Testing strategy
7. **[SUPABASE_SEEDING.md](SUPABASE_SEEDING.md)** - Database seeding guide
8. **[LARAVEL_BOOST_INSTALLATION.md](LARAVEL_BOOST_INSTALLATION.md)** - Dev tooling setup

## Quick Reference

### Architecture at a Glance

- **Authentication**: Lark OAuth → Laravel validates → Laravel generates Supabase JWT
- **JWT Validation**: Next.js BFF validates on every request
- **Authorization**: Supabase RLS policies
- **Business Logic**: Laravel Domain Services
- **Module Communication**: Contracts & Events only

### Key Files

- [Architecture Decisions](ARCHITECTURE-DECISIONS.md) - Authoritative source for "why"
- [System Architecture](Backend-System-Architecture.md) - Authoritative source for "what"
- [Security Model](TRUSTED_BFF_MIDDLEWARE.md) - Authoritative source for "how"

### Not Used

- ❌ Laravel Sanctum (replaced by Supabase JWT)
- ❌ Spatie Permission (replaced by Supabase RLS)
- ❌ Laravel Policies for auth (use Domain Rules for business logic)

