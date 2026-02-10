# Supabase SQL Seeding Guide

This guide explains how to seed the Supabase database using SQL files in a safe, automated way.

## Overview

The project uses SQL files for seeding Supabase database infrastructure and initial data. This approach is recommended because:

- ✅ Direct access to `auth` schema (for creating users)
- ✅ Proper password hashing using PostgreSQL's `crypt()` function
- ✅ Version controlled SQL files
- ✅ Automated execution via Artisan command
- ✅ Built-in safety checks to prevent data loss

## Quick Start

### 1. Create SQL Seed File

Create a file in `database/seeds/sql/` following the naming convention:

```bash
# Example: database/seeds/sql/001_initial_setup.sql
```

### 2. Write Your SQL

```sql
-- Example: Create initial super admin
INSERT INTO auth.users (
    instance_id, id, aud, role, email,
    encrypted_password, email_confirmed_at,
    created_at, updated_at, raw_app_meta_data,
    raw_user_meta_data, is_super_admin
)
VALUES (
    '00000000-0000-0000-0000-000000000000',
    gen_random_uuid(),
    'authenticated',
    'authenticated',
    'admin@belive.com',
    crypt('YourSecurePassword123!', gen_salt('bf')),
    NOW(),
    NOW(),
    NOW(),
    '{"provider":"email","providers":["email"]}',
    '{}',
    true
);
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
│           ├── 002_rls_policies.sql
│           └── 003_test_data.sql
```

## Naming Convention

- Use numbered prefixes: `001_description.sql`, `002_description.sql`
- Files are executed in alphabetical order
- Use descriptive names: `001_initial_setup.sql`, `002_rls_policies.sql`

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

### Password Hashing

Always use PostgreSQL's `crypt()` function for passwords:

```sql
-- ✅ Correct
encrypted_password = crypt('password', gen_salt('bf'))

-- ❌ Wrong (plain text)
encrypted_password = 'password'
```

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

### Example 1: Initial Super Admin

```sql
-- database/seeds/sql/001_initial_setup.sql

-- Create super admin in auth.users
INSERT INTO auth.users (
    instance_id, id, aud, role, email,
    encrypted_password, email_confirmed_at,
    created_at, updated_at, raw_app_meta_data,
    raw_user_meta_data, is_super_admin
)
VALUES (
    '00000000-0000-0000-0000-000000000000',
    gen_random_uuid(),
    'authenticated',
    'authenticated',
    'admin@belive.com',
    crypt('SecurePassword123!', gen_salt('bf')),
    NOW(),
    NOW(),
    NOW(),
    '{"provider":"email","providers":["email"]}',
    '{}',
    true
)
ON CONFLICT (email) DO NOTHING;

-- Create corresponding profile
INSERT INTO public.users (id, email, name, role, created_at, updated_at)
SELECT 
    id,
    email,
    'Super Admin',
    'super_admin',
    NOW(),
    NOW()
FROM auth.users
WHERE email = 'admin@belive.com'
ON CONFLICT (id) DO NOTHING;
```

### Example 2: RLS Policies

```sql
-- database/seeds/sql/002_rls_policies.sql

-- Enable RLS on users table
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;

-- Policy: Users can view their own data
CREATE POLICY "users_select_own"
ON public.users FOR SELECT
USING (auth.uid()::text = id::text);

-- Policy: Users can update their own data
CREATE POLICY "users_update_own"
ON public.users FOR UPDATE
USING (auth.uid()::text = id::text);
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

## Related Documentation

- [Backend System Architecture](Backend-System-Architecture.md) - Database structure
- [Architecture Decisions](ARCHITECTURE-DECISIONS.md) - Design decisions
- [Module Structure](MODULE_STRUCTURE.md) - Code organization

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
- ❌ **NEVER** modify or delete data in `auth.users` table
- ✅ **ONLY** use INSERT, UPDATE (with WHERE), CREATE statements
- ✅ **ALWAYS** use transactions for data modifications
- ✅ **ALWAYS** verify SQL files before executing

