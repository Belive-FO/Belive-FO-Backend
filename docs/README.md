# BeLive FlowOffice - Documentation Index

## Reading Order for New Developers / AI Agents

Follow this sequence to understand the system architecture:

1. **[ARCHITECTURE.md](ARCHITECTURE.md)** - Architecture decisions and system design
2. **[IMPLEMENTATION.md](IMPLEMENTATION.md)** - Implementation roadmap and code examples
3. **[DEVELOPMENT.md](DEVELOPMENT.md)** - Testing strategy and database seeding

## Quick Reference

### Architecture at a Glance

- **Authentication**: Lark OAuth → Laravel validates → Laravel creates session (Sanctum SPA)
- **Authorization**: Laravel Policies + Spatie Permission (RBAC)
- **Business Validation**: Domain Rules (separate from authorization)
- **Database**: Supabase PostgreSQL (plain database, no RLS)
- **Storage**: Supabase Storage (S3-compatible)
- **Business Logic**: Laravel Domain Services
- **Module Communication**: Contracts & Events only

### Components Used

- ✅ Laravel Sanctum (SPA mode for session-based authentication)
- ✅ Spatie Permission (roles and permissions management)
- ✅ Laravel Policies (authorization checks)
- ✅ Domain Rules (business validation, separate from authorization)

### Key Documents

- [Architecture](ARCHITECTURE.md) - Complete architecture documentation (ADRs + System Design)
- [Implementation](IMPLEMENTATION.md) - Step-by-step implementation guide
- [Development](DEVELOPMENT.md) - Testing and database seeding guides
