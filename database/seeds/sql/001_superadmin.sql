-- ============================================
-- Super Admin User Seed
-- ============================================
-- Creates initial superadmin user in auth.users and public.users
-- Email: superadmin@example.com
-- Password: password
-- ============================================

-- Step 1: Update existing user or create new super admin in auth.users
-- Using pre-computed bcrypt hash for password "password"
-- Note: Supabase uses bcrypt for password hashing

-- First, try to update existing user
UPDATE auth.users
SET 
    encrypted_password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    email_confirmed_at = NOW(),
    is_super_admin = true,
    updated_at = NOW(),
    raw_app_meta_data = '{"provider":"email","providers":["email"]}'
WHERE email = 'superadmin@example.com';

-- If no user exists, create one
INSERT INTO auth.users (
    instance_id,
    id,
    aud,
    role,
    email,
    encrypted_password,
    email_confirmed_at,
    created_at,
    updated_at,
    raw_app_meta_data,
    raw_user_meta_data,
    is_super_admin,
    confirmation_token,
    email_change,
    email_change_token_new,
    recovery_token
)
SELECT 
    '00000000-0000-0000-0000-000000000000',
    gen_random_uuid(),
    'authenticated',
    'authenticated',
    'superadmin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    NOW(),
    NOW(),
    NOW(),
    '{"provider":"email","providers":["email"]}',
    '{}',
    true,
    '',
    '',
    '',
    ''
WHERE NOT EXISTS (SELECT 1 FROM auth.users WHERE email = 'superadmin@example.com');

-- Step 2: Create corresponding profile in public.users
-- First, try to update existing user
UPDATE public.users
SET 
    email = (SELECT email FROM auth.users WHERE email = 'superadmin@example.com'),
    name = 'Super Admin',
    role = 'super_admin'
WHERE id = (SELECT id FROM auth.users WHERE email = 'superadmin@example.com');

-- If no user exists, create one
INSERT INTO public.users (id, email, name, role, created_at)
SELECT 
    id,
    email,
    'Super Admin',
    'super_admin',
    NOW()
FROM auth.users
WHERE email = 'superadmin@example.com'
AND NOT EXISTS (SELECT 1 FROM public.users WHERE id = (SELECT id FROM auth.users WHERE email = 'superadmin@example.com'));

-- Step 3: Verify the user was created
SELECT 
    'Superadmin created successfully!' as message,
    id,
    email,
    is_super_admin,
    email_confirmed_at
FROM auth.users
WHERE email = 'superadmin@example.com';

