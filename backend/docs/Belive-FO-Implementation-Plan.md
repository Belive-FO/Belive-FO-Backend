# BeLive FlowOffice - Implementation Plan & Architecture

> **Complete implementation guide with detailed diagrams, tech stack, and key concept explanations**

---

## Table of Contents

1. [Complete Tech Stack](#complete-tech-stack)
2. [Key Concepts Explained](#key-concepts-explained)
3. [Detailed Architecture Diagrams](#detailed-architecture-diagrams)
4. [Implementation Roadmap](#implementation-roadmap)
5. [Module Implementation Guides](#module-implementation-guides)

---

## Complete Tech Stack

### Backend Dependencies

#### 1. Core Framework
```bash
composer create-project laravel/laravel belive-flowoffice
cd belive-flowoffice
```

#### 2. Essential Packages

**Supabase Bridge (CRITICAL)**
```bash
composer require saeedvir/supabase
```

**What it does:**
- Enables Laravel to generate Supabase-compatible JWT tokens
- Connects Laravel to Supabase Postgres database
- Enables Realtime subscriptions from Laravel
- Manages Supabase Storage (file uploads/downloads)

**Configuration:**
```php
// config/supabase.php
return [
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_KEY'),
    'secret' => env('SUPABASE_SECRET'),
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),
];
```

```env
# .env
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_SECRET=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_JWT_SECRET=your-super-secret-jwt-secret-at-least-32-characters
```

**Usage Examples:**

```php
use Supabase\Supabase;

// 1. Query Supabase database
$supabase = new Supabase();
$attendance = $supabase->from('attendance')
    ->select('*')
    ->eq('user_id', $userId)
    ->gte('clocked_at', now()->startOfMonth())
    ->execute();

// 2. Upload to Supabase Storage
use Illuminate\Support\Facades\Storage;

$path = Storage::disk('supabase')
    ->putFile('receipts/' . $userId, $request->file('receipt'));

// 3. Generate signed URL (expires in 1 hour)
$url = Storage::disk('supabase')
    ->temporaryUrl($path, now()->addHour());

// 4. Listen to Realtime changes
$supabase->realtime()
    ->channel('attendance-changes')
    ->on('INSERT', 'public', 'attendance', function($payload) {
        broadcast(new AttendanceRecorded($payload['record']));
    })
    ->subscribe();
```

**Laravel Boost (AI Development Assistant)**
```bash
composer require laravel/boost --dev
```

**What it does:**
- Generates context files for AI assistants (Claude Code, Cursor, GitHub Copilot)
- Improves AI understanding of your Laravel project structure
- Helps AI generate better migrations, models, and controllers

**Usage:**
```bash
# Generate context for AI
php artisan boost:context

# This creates .boost/context.json containing:
# - Your database schema
# - Existing models and relationships
# - Route definitions
# - Policy structure
```

**How to use with AI:**
```
Prompt: "Using Laravel Boost context, create a Leave model with:
- Relationship to User
- Belongs to manager (User)  
- Has approval_status enum
- Activity logging enabled"
```

**Audit Trail**
```bash
composer require spatie/laravel-activitylog
```

> **Note:** Laravel Sanctum and Spatie Permissions are **NOT used** in this architecture.
- **Authentication**: Laravel validates Lark OAuth and generates JWT tokens
- **Authorization**: Supabase RLS policies use JWT tokens to filter data
- **JWT Validation**: Next.js BFF validates JWT on subsequent requests
See `Backend-System-Architecture.md` for details on the Supabase-first architecture.

### Frontend Dependencies

```bash
npx create-next-app@latest belive-fo-frontend --typescript --tailwind --app
cd belive-fo-frontend

# Supabase client for Realtime
npm install @supabase/supabase-js

# State management
npm install @tanstack/react-query zustand

# Forms
npm install react-hook-form @hookform/resolvers zod

# UI components
npx shadcn-ui@latest init
npx shadcn-ui@latest add button form input calendar select table
```

### Infrastructure Services

| Service | Purpose | Cost |
|---------|---------|------|
| **Vercel** | Next.js hosting | Free tier available |
| **Supabase** | Database + Realtime + Storage | Free tier: 500MB DB, 1GB storage |
| **Lark/Feishu** | OAuth + Approvals + Mobile | Free for companies <500 employees |

---

## Key Concepts Explained

### 1. What is Row-Level Security (RLS)?

**Simple Explanation:**
Row-Level Security is like having a security guard at the database level who checks "Is this person allowed to see this specific row of data?"

**Example:**

Without RLS:
```sql
-- Anyone with database access can see ALL attendance records
SELECT * FROM attendance;
-- Returns: All 10,000 employee attendance records
```

With RLS:
```sql
-- Create policy: Users can only see their own records
CREATE POLICY "users_see_own_attendance"
ON attendance FOR SELECT
USING (auth.uid()::text = user_id::text);

-- Now when Employee #123 queries:
SELECT * FROM attendance;
-- Returns: Only Employee #123's records (automatic filtering!)
```

**Why this matters:**

```
❌ Without RLS:
Next.js → Supabase query → Returns ALL data
         → Next.js must filter in JavaScript
         → Security risk if filtering code has bugs

✅ With RLS:
Next.js → Supabase query → Database filters automatically
         → Impossible to bypass (even with SQL injection)
         → Returns only authorized data
```

**Real-world scenario:**

```
Manager tries to view team attendance:

1. Manager logs in → Gets JWT with { user_id: "456", role: "manager" }
2. Next.js queries: SELECT * FROM attendance
3. RLS policy checks:
   - Is auth.uid() = user_id? NO
   - Is user a manager of this employee? YES (separate policy)
   - Returns: Only team members' data

Employee tries same query:
1. Employee logs in → Gets JWT with { user_id: "123", role: "employee" }
2. Next.js queries: SELECT * FROM attendance  
3. RLS policy checks:
   - Is auth.uid() = user_id? YES
   - Returns: Only their own data
```

**Our RLS Strategy:**

```sql
-- Defense in Depth: Simple rules only
CREATE POLICY "employees_read_own_attendance"
ON attendance FOR SELECT
USING (auth.uid()::text = user_id::text);

-- Laravel remains authoritative for complex rules
-- Example: "Manager can approve IF leave < 5 days AND not unpaid"
-- ↑ This stays in PHP (too complex for SQL)
```

### 2. What is Vendor Independence?

**Simple Explanation:**
Vendor independence means your business logic doesn't directly depend on a specific vendor's API. You can swap vendors without rewriting your core code.

**❌ Vendor Dependent (Bad):**

```php
// Leave approval logic DIRECTLY calls Lark API
class LeaveService {
    public function approve(Leave $leave) {
        // Business logic mixed with vendor API
        Http::post('https://open.larksuite.com/approval', [...]);
        
        $leave->update(['status' => 'approved']);
    }
}

// Problem: If you switch from Lark to Slack, you must:
// 1. Find every Lark API call in your codebase
// 2. Rewrite business logic
// 3. Risk breaking domain rules
```

**✅ Vendor Independent (Good):**

```php
// Interface (your design, vendor-agnostic)
interface ApprovalProviderInterface {
    public function createApproval(ApprovalRequest $request): string;
    public function getStatus(string $id): ApprovalStatus;
}

// Lark implementation
class LarkApprovalAdapter implements ApprovalProviderInterface {
    public function createApproval(ApprovalRequest $request): string {
        // All Lark-specific code contained here
        return Http::post('https://open.larksuite.com/approval', [...])->json('id');
    }
}

// Slack implementation (future)
class SlackApprovalAdapter implements ApprovalProviderInterface {
    public function createApproval(ApprovalRequest $request): string {
        // All Slack-specific code contained here
        return Http::post('https://slack.com/api/approve', [...])->json('id');
    }
}

// Your domain code (never changes)
class LeaveService {
    public function __construct(
        private ApprovalProviderInterface $approvalProvider
    ) {}
    
    public function approve(Leave $leave) {
        // Business logic stays clean
        $this->approvalProvider->createApproval(...);
        $leave->update(['status' => 'approved']);
    }
}

// Config (switch vendors in 1 line)
// config/services.php
'approval_provider' => env('APPROVAL_PROVIDER', 'lark'), // or 'slack'
```

**Benefits:**

```
Scenario 1: Lark has outage
→ Switch to EmailApprovalAdapter
→ Zero downtime
→ Business continues

Scenario 2: Company switches to Microsoft Teams
→ Build TeamsApprovalAdapter
→ Swap in config file
→ Domain code untouched

Scenario 3: Testing
→ Use FakeApprovalAdapter
→ No API calls during tests
→ Fast, reliable tests
```

### 3. What is Vendor Lock-in?

**Simple Explanation:**
Vendor lock-in happens when leaving a vendor requires rewriting significant portions of your application.

**Example:**

```
Scenario: You built everything using Lark's proprietary features

Your codebase:
├─ LeaveController.php (calls Lark API directly)
├─ AttendanceService.php (uses Lark GPS format)
├─ ClaimRules.php (validates using Lark approval response format)
└─ UserModel.php (stores data in Lark's structure)

CFO: "We're switching to Microsoft Teams next month"
You: "That will take 6 months and cost $200,000 to rewrite"
CFO: 😱

This is vendor lock-in.
```

**How We Avoid It:**

```
Our architecture:

├─ Domain/
│  ├─ LeaveRules.php          ← Pure business logic (vendor-agnostic)
│  └─ AttendanceService.php   ← Pure business logic (vendor-agnostic)
│
├─ Adapters/
│  ├─ LarkApprovalAdapter.php     ← Only touches Lark
│  ├─ LarkIdentityAdapter.php     ← Only touches Lark
│  └─ LarkNotificationAdapter.php ← Only touches Lark
│
└─ Config/
   └─ services.php  ← Switch vendors here

Switch scenario:
CFO: "We're switching to Microsoft Teams"
You: "I'll build TeamsAdapters. Ready in 2 weeks, $10,000 cost"
CFO: 😊

Domain layer never changes.
```

**Lock-in vs Independence Comparison:**

| Aspect | Vendor Lock-in | Vendor Independence |
|--------|---------------|---------------------|
| **API calls** | Scattered throughout codebase | Isolated in adapters |
| **Data format** | Vendor's structure used everywhere | Transformed at boundaries |
| **Business rules** | Mixed with vendor logic | Pure, vendor-agnostic |
| **Testing** | Requires live API access | Mockable interfaces |
| **Migration cost** | 6-12 months | 2-4 weeks |
| **Migration risk** | High (touching core logic) | Low (swap adapters only) |

---

## Detailed Architecture Diagrams

### Diagram 1: System Architecture Overview

```
┌───────────────────────────────────────────────────────────────────────┐
│                          EMPLOYEE'S PHONE                              │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                  Lark Mobile App (Native)                         │ │
│  │                                                                   │ │
│  │  ┌───────────┐  ┌──────────────┐  ┌───────────────────────────┐ │ │
│  │  │ Lark Chat │  │   Approval   │  │  BeLive FO (h5_sdk)       │ │ │
│  │  │           │  │    Inbox     │  │                           │ │ │
│  │  └───────────┘  └──────────────┘  └───────────┬───────────────┘ │ │
│  │                                                │                 │ │
│  │  Native Capabilities (via tt.* API):          │                 │ │
│  │  ✅ tt.getLocation() → GPS (precise)           │                 │ │
│  │  ✅ tt.chooseImage() → Camera                  │                 │ │
│  │  ✅ tt.getWifiStatus() → WiFi SSID             │                 │ │
│  └────────────────────────────────────────────────┼─────────────────┘ │
│                                                   │                   │
└───────────────────────────────────────────────────┼───────────────────┘
                                                    │
                                                    │ OAuth + JS SDK
                                                    ▼
┌───────────────────────────────────────────────────────────────────────┐
│                      NEXT.JS FRONTEND (Vercel)                         │
│                   https://belive.company.com                           │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                        UI LAYER                                   │ │
│  │  ┌───────────────┐ ┌──────────────┐ ┌─────────────────────────┐ │ │
│  │  │  Attendance   │ │    Leave     │ │       Claims            │ │ │
│  │  │  Pages        │ │    Pages     │ │       Pages             │ │ │
│  │  └───────────────┘ └──────────────┘ └─────────────────────────┘ │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                   STATE MANAGEMENT                                │ │
│  │                                                                   │ │
│  │  ┌─────────────────────────┐  ┌───────────────────────────────┐ │ │
│  │  │  TanStack Query         │  │  Zustand                      │ │ │
│  │  │  (Server State)         │  │  (UI State)                   │ │ │
│  │  │                         │  │                               │ │ │
│  │  │  • API response cache   │  │  • Sidebar open/close         │ │ │
│  │  │  • Deduplication        │  │  • Selected theme             │ │ │
│  │  │  • Auto refetch         │  │  • Form drafts (local only)   │ │ │
│  │  │  • Optimistic updates   │  │  • Last visited page          │ │ │
│  │  └─────────────────────────┘  └───────────────────────────────┘ │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │           SUPABASE CLIENT (@supabase/supabase-js)                │ │
│  │                                                                   │ │
│  │  const supabase = createClient(url, key, {                       │ │
│  │    global: {                                                     │ │
│  │      headers: { Authorization: `Bearer ${supabaseJWT}` }         │ │
│  │    }                                                             │ │
│  │  })                                                              │ │
│  │                                                                   │ │
│  │  • Realtime subscriptions (WebSocket)                            │ │
│  │  • Storage.from('receipts').download()                           │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
└─────┬──────────────────────────────────────────────────┬─────────────┘
      │                                                  │
      │ HTTPS REST                                       │ WebSocket
      │ X-User-ID: 123 (from Supabase JWT)               │ (Realtime)
      │ X-Internal-Key: [shared secret]                  │
      │                                                  │
      ▼                                                  ▼
┌───────────────────────────────────────────────────────────────────────┐
│              LARAVEL API (DOMAIN LOGIC ENGINE)                        │
│                  https://api.belive.com                                │
│                                                                        │
│  Dependencies:                                                         │
│  ├─ saeedvir/supabase (Realtime + Storage bridge)                    │
│  ├─ spatie/laravel-activitylog (Audit)                                │
│  └─ laravel/boost --dev (AI context - dev only)               │
│                                                                        │
│  Note: Laravel Sanctum and Spatie Permissions are NOT used.           │
│  Authentication/authorization handled by Supabase (JWT + RLS).       │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                     ADAPTER LAYER                                 │ │
│  │              (Vendor Independence Pattern)                        │ │
│  │                                                                   │ │
│  │  ┌──────────────────┐  ┌─────────────────┐  ┌────────────────┐  │ │
│  │  │ Approval         │  │ Notification    │  │ Identity       │  │ │
│  │  │ Provider         │  │ Provider        │  │ Provider       │  │ │
│  │  │                  │  │                 │  │                │  │ │
│  │  │ Interface        │  │ Interface       │  │ Interface      │  │ │
│  │  │ ├─ Lark          │  │ ├─ LarkBot      │  │ ├─ LarkOAuth  │  │ │
│  │  │ └─ Email (backup)│  │ └─ Email        │  │ └─ Database   │  │ │
│  │  └──────────────────┘  └─────────────────┘  └────────────────┘  │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │            WEBHOOK HANDLERS (Anti-Corruption Layer)              │ │
│  │                                                                   │ │
│  │  Lark Webhook → Verify Signature → Transform → Dispatch Event   │ │
│  │                                                                   │ │
│  │  Example:                                                        │ │
│  │  POST /webhooks/lark/approval                                    │ │
│  │  {                                                               │ │
│  │    "event_type": "approval.approved",                            │ │
│  │    "instance_code": "xxx",                                       │ │
│  │    "operator_id": "ou_xxx"                                       │ │
│  │  }                                                               │ │
│  │       ↓ Transform                                                │ │
│  │  event(new LeaveApprovalReceived(                                │ │
│  │    leaveId: 123,                                                 │ │
│  │    approverId: 45,                                               │ │
│  │    decision: APPROVED                                            │ │
│  │  ))                                                              │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                   DOMAIN MODULES                                  │ │
│  │              (Business Logic - Vendor Agnostic)                   │ │
│  │                                                                   │ │
│  │  Attendance/                Leave/                 Claims/        │ │
│  │  ├─ Domain/                 ├─ Domain/             ├─ Domain/    │ │
│  │  │  ├─ Rules.php            │  ├─ Rules.php        │  ├─ Rules   │ │
│  │  │  ├─ Service.php          │  ├─ Service.php      │  └─ Service │ │
│  │  │  └─ Events/              │  └─ Events/          │             │ │
│  │  ├─ Application/            ├─ Application/        ├─ App/       │ │
│  │  │  └─ Handlers/            │  └─ Handlers/        │  └─ OCR/    │ │
│  │  └─ Infrastructure/         └─ Infrastructure/     └─ Infra/     │ │
│  │     └─ SupabaseRepo.php        └─ SupabaseRepo       └─ Repo    │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │                 CROSS-CUTTING CONCERNS                            │ │
│  │                                                                   │ │
│  │  • Supabase JWT → User identity (validated by Next.js BFF)      │ │
│  │  • Supabase RLS → Row-level access control                       │ │
│  │  • Spatie Activity Log → Audit trail (automatic)                 │ │
│  │  • Laravel Events → Domain event system                          │ │
│  │  • saeedvir/supabase → DB + Realtime + Storage access            │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
└───┬────────────────────────────────────────────────────────┬──────────┘
    │                                                        │
    │ Eloquent ORM                                           │ HTTP
    │ saeedvir/supabase                                      │ Webhooks
    │                                                        │
    ▼                                                        ▼
┌───────────────────────────┐              ┌─────────────────────────────┐
│      SUPABASE             │              │      LARK PLATFORM          │
│    (Subordinate)          │              │      (Peripheral)           │
│                           │              │                             │
│  ┌─────────────────────┐  │              │  ┌───────────────────────┐  │
│  │  Postgres Database  │  │              │  │  OAuth 2.0            │  │
│  │                     │  │              │  │  • lark_user_id       │  │
│  │  Tables:            │  │              │  │  • org_id             │  │
│  │  ├─ users           │  │              │  └───────────────────────┘  │
│  │  ├─ attendance      │  │              │                             │
│  │  ├─ leaves          │  │              │  ┌───────────────────────┐  │
│  │  ├─ claims          │  │              │  │  Approval Engine      │  │
│  │  └─ activity_log    │  │              │  │  • Visual workflow    │  │
│  │                     │  │              │  │  • Multi-step support │  │
│  │  RLS Policies:      │  │              │  │  • Audit history      │  │
│  │  ✅ Row filtering    │  │              │  └───────────────────────┘  │
│  │  ✅ Defense in depth │  │              │                             │
│  └─────────────────────┘  │              │  ┌───────────────────────┐  │
│                           │              │  │  Webhooks             │  │
│  ┌─────────────────────┐  │              │  │  • Signed payloads    │  │
│  │  Realtime           │  │              │  │  • Event types:       │  │
│  │  (WebSocket)        │  │              │  │    - approved         │  │
│  │                     │  │              │  │    - rejected         │  │
│  │  • postgres_changes │  │              │  └───────────────────────┘  │
│  │  • RLS-aware        │  │              │                             │
│  │  • Auto-broadcast   │  │              │  ┌───────────────────────┐  │
│  └─────────────────────┘  │              │  │  JS SDK (h5_sdk)      │  │
│                           │              │  │  • tt.getLocation()   │  │
│  ┌─────────────────────┐  │              │  │  • tt.chooseImage()   │  │
│  │  Storage            │  │              │  │  • tt.getWifiStatus() │  │
│  │  (S3-compatible)    │  │              │  └───────────────────────┘  │
│  │                     │  │              │                             │
│  │  Buckets:           │  │              │  ┌───────────────────────┐  │
│  │  ├─ receipts        │  │              │  │  Chat Bot API         │  │
│  │  ├─ avatars         │  │              │  │  • Send notifications │  │
│  │  └─ documents       │  │              │  │  • Rich cards         │  │
│  └─────────────────────┘  │              │  └───────────────────────┘  │
│                           │              │                             │
└───────────────────────────┘              └─────────────────────────────┘
```

### Diagram 2: Clock-In Flow (Attendance)

```
═══════════════════════════════════════════════════════════════════════
                       ATTENDANCE CLOCK-IN FLOW
              (Demonstrating Lark GPS + Laravel Validation)
═══════════════════════════════════════════════════════════════════════

STEP 1: User Initiates Clock-In
─────────────────────────────────

   ┌──────────────┐
   │  Employee    │
   │  Phone       │
   └──────┬───────┘
          │ Taps "Clock In" button
          ▼
   ┌──────────────────────────┐
   │  Next.js Component       │
   │  <ClockInButton />       │
   └──────────┬───────────────┘
              │ onClick
              ▼
   ┌──────────────────────────────────────────┐
   │  Call Lark JS SDK                        │
   │                                          │
   │  const location = await tt.getLocation({ │
   │    type: 'gcj02'  // Chinese GPS system  │
   │  })                                      │
   │                                          │
   │  const wifi = await tt.getWifiStatus()   │
   └──────────┬───────────────────────────────┘
              │
              │ Returns:
              │ {
              │   latitude: 3.1390,
              │   longitude: 101.6869,
              │   accuracy: 5,      // meters
              │   wifi_ssid: "OfficeWiFi"
              │ }
              │
              ▼

STEP 2: Send to Laravel API
────────────────────────────

   ┌──────────────────────────────────────────┐
   │  POST /api/attendance/clock-in           │
   │                                          │
   │  Headers:                                │
   │    Authorization: Bearer xxx...          │
   │                                          │
   │  Body:                                   │
   │  {                                       │
   │    latitude: 3.1390,                     │
   │    longitude: 101.6869,                  │
   │    wifi_ssid: "OfficeWiFi"               │
   │  }                                       │
   └──────────┬───────────────────────────────┘
              │
              ▼

STEP 3: Laravel Validates (AUTHORITATIVE)
──────────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  AttendanceController                                 │
   │                                                       │
   │  1. TrustedBffMiddleware validates                    │
   │     ✅ X-Internal-Key matches shared secret           │
   │     ✅ X-User-ID extracted from Supabase JWT         │
   │                                                       │
   │  2. Dispatch to Handler                               │
   │     $handler->handle(new ClockInCommand(...))         │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  ClockInHandler (Application Layer)                   │
   │                                                       │
   │  Business Validation:                                 │
   │                                                       │
   │  ┌─────────────────────────────────────────────────┐  │
   │  │ 1. Geofence Check (AttendancePolicy)           │  │
   │  │                                                │  │
   │  │    Office location: (3.1390, 101.6869)         │  │
   │  │    User location:   (3.1395, 101.6870)         │  │
   │  │    Distance: 78 meters                         │  │
   │  │    Geofence radius: 200 meters                 │  │
   │  │    ✅ PASS                                      │  │
   │  └─────────────────────────────────────────────────┘  │
   │                                                       │
   │  ┌─────────────────────────────────────────────────┐  │
   │  │ 2. Duplicate Check                             │  │
   │  │                                                │  │
   │  │    Query: attendance                           │  │
   │  │    WHERE user_id = 123                         │  │
   │  │    AND DATE(clocked_at) = '2026-02-06'         │  │
   │  │    AND clocked_out_at IS NULL                  │  │
   │  │    ❌ NO RECORD FOUND                           │  │
   │  │    ✅ PASS (can clock in)                       │  │
   │  └─────────────────────────────────────────────────┘  │
   │                                                       │
   │  ┌─────────────────────────────────────────────────┐  │
   │  │ 3. WiFi Validation (Optional)                  │  │
   │  │                                                │  │
   │  │    Expected: "OfficeWiFi"                      │  │
   │  │    Received: "OfficeWiFi"                      │  │
   │  │    ✅ PASS                                      │  │
   │  └─────────────────────────────────────────────────┘  │
   │                                                       │
   │  All validations passed ✅                            │
   │  → Call AttendanceService::clockIn()                 │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼

STEP 4: Save to Supabase
─────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  AttendanceService (Domain Layer)                     │
   │                                                       │
   │  $attendance = Attendance::create([                   │
   │    'user_id' => 123,                                  │
   │    'clocked_at' => '2026-02-06 08:30:15',             │
   │    'latitude' => 3.1395,                              │
   │    'longitude' => 101.6870,                           │
   │    'wifi_ssid' => 'OfficeWiFi',                       │
   │    'device_accuracy' => 5                             │
   │  ]);                                                  │
   │                                                       │
   │  event(new AttendanceClockedIn($attendance));         │
   └───────────────────────┬───────────────────────────────┘
                           │ Uses saeedvir/supabase
                           │ under the hood
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  Supabase Postgres Database                           │
   │                                                       │
   │  INSERT INTO attendance (                             │
   │    user_id, clocked_at, latitude, longitude, ...      │
   │  ) VALUES (                                           │
   │    123, '2026-02-06 08:30:15', 3.1395, 101.6870, ...  │
   │  )                                                    │
   │                                                       │
   │  ✅ Record inserted                                    │
   │  🔔 Postgres trigger fires: NOTIFY attendance_changes │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼

STEP 5: Realtime Broadcast
───────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  Supabase Realtime (WebSocket Server)                 │
   │                                                       │
   │  Broadcast to all subscribed clients:                 │
   │  {                                                    │
   │    event: "INSERT",                                   │
   │    schema: "public",                                  │
   │    table: "attendance",                               │
   │    record: {                                          │
   │      id: 9876,                                        │
   │      user_id: 123,                                    │
   │      clocked_at: "2026-02-06T08:30:15Z",              │
   │      ...                                              │
   │    }                                                  │
   │  }                                                    │
   │                                                       │
   │  RLS filters: Only send to user_id = 123              │
   └───────────────────────┬───────────────────────────────┘
                           │ WebSocket
                           ▼

STEP 6: Next.js Receives Update
────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  Next.js Supabase Client                              │
   │                                                       │
   │  supabase                                             │
   │    .channel('attendance-changes')                     │
   │    .on('postgres_changes', {                          │
   │      event: 'INSERT',                                 │
   │      schema: 'public',                                │
   │      table: 'attendance'                              │
   │    }, (payload) => {                                  │
   │      // Update TanStack Query cache                   │
   │      queryClient.setQueryData(...)                    │
   │    })                                                 │
   │    .subscribe()                                       │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  UI Component Re-renders                              │
   │                                                       │
   │  ╔══════════════════════════════════════════════════╗ │
   │  ║  ✅ Clock-in Successful!                          ║ │
   │  ║                                                  ║ │
   │  ║  Time: 8:30 AM                                   ║ │
   │  ║  Location: Office (78m accuracy)                 ║ │
   │  ║                                                  ║ │
   │  ║  [Clock Out]                                     ║ │
   │  ╚══════════════════════════════════════════════════╝ │
   └───────────────────────────────────────────────────────┘

TOTAL TIME: ~400-600ms

Security Layers Applied:
✅ Lark native GPS (hard to spoof)
✅ Supabase JWT authentication (validated by Next.js BFF)
✅ TrustedBffMiddleware (validates internal API calls)
✅ Domain rules validation (geofence, duplicate check)
✅ Supabase RLS (row-level access control)
✅ Audit log (automatic via Spatie Activity Log)
```

### Diagram 3: Leave Approval Flow

```
═══════════════════════════════════════════════════════════════════════
                       LEAVE APPROVAL FLOW
           (Demonstrating Adapter Pattern + Webhooks)
═══════════════════════════════════════════════════════════════════════

STEP 1: Employee Submits Leave
───────────────────────────────

   ┌──────────────┐
   │  Employee    │
   │  fills form  │
   └──────┬───────┘
          │
          ▼
   ┌─────────────────────────────────────┐
   │  Next.js LeaveRequestForm           │
   │                                     │
   │  Leave Type: Annual                 │
   │  Start: 2026-03-01                  │
   │  End: 2026-03-05                    │
   │  Days: 5                            │
   │  Reason: Family vacation            │
   │                                     │
   │  [Submit] ───────────────────────►  │
   └─────────────────────────────────────┘
          │
          │ POST /api/leave/submit
          ▼

STEP 2: Laravel Validates & Creates Approval
─────────────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  SubmitLeaveHandler (Application Layer)               │
   │                                                       │
   │  1. Domain Validation (LeaveRules)                   │
   │     ┌───────────────────────────────────────────┐     │
   │     │ Check balance:                           │     │
   │     │ User has: 12 days annual leave           │     │
   │     │ Requesting: 5 days                       │     │
   │     │ ✅ Sufficient balance                     │     │
   │     └───────────────────────────────────────────┘     │
   │                                                       │
   │  2. Create Leave Record                               │
   │     ┌───────────────────────────────────────────┐     │
   │     │ Leave::create([                          │     │
   │     │   'user_id' => 123,                      │     │
   │     │   'leave_type' => 'annual',              │     │
   │     │   'start_date' => '2026-03-01',          │     │
   │     │   'end_date' => '2026-03-05',            │     │
   │     │   'days' => 5,                           │     │
   │     │   'status' => 'pending_approval'         │     │
   │     │ ])                                       │     │
   │     │ → Saved to Supabase (ID: 456)            │     │
   │     └───────────────────────────────────────────┘     │
   │                                                       │
   │  3. Create Approval (via Adapter)                     │
   │     ┌───────────────────────────────────────────┐     │
   │     │ $approvalRequest = new ApprovalRequest(  │     │
   │     │   approvalCode: 'leave_request',         │     │
   │     │   userId: $user->lark_user_id,           │     │
   │     │   formData: [                            │     │
   │     │     'leave_id' => 456,                   │     │
   │     │     'employee' => 'John Doe',            │     │
   │     │     'type' => 'Annual Leave',            │     │
   │     │     'dates' => '1-5 March 2026',         │     │
   │     │     'days' => 5                          │     │
   │     │   ],                                     │     │
   │     │   approverIds: ['ou_manager_xxx']        │     │
   │     │ );                                       │     │
   │     │                                          │     │
   │     │ $larkId = $this->approvalProvider       │     │
   │     │   ->createApproval($approvalRequest);    │     │
   │     └───────────────────────────────────────────┘     │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  LarkApprovalAdapter (Infrastructure)                 │
   │                                                       │
   │  Http::withToken($accessToken)                        │
   │    ->post('https://open.larksuite.com/approval', [    │
   │      'approval_code' => 'leave_request',              │
   │      'user_id' => 'ou_employee_xxx',                  │
   │      'form' => [                                      │
   │        ['id' => 'leave_id', 'value' => '456'],        │
   │        ['id' => 'employee', 'value' => 'John Doe'],   │
   │        ...                                            │
   │      ],                                               │
   │      'node_approver_id_list' => [['ou_manager_xxx']]  │
   │    ])                                                 │
   │                                                       │
   │  Returns: { instance_code: 'abc123' }                 │
   └───────────────────────┬───────────────────────────────┘
                           │
                           │ Updates leave record:
                           │ lark_approval_id = 'abc123'
                           ▼

STEP 3: Manager Sees Approval in Lark Chat
───────────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  Manager's Lark Chat                                  │
   │                                                       │
   │  ┌─────────────────────────────────────────────────┐  │
   │  │  🔔 New Approval Request                        │  │
   │  │                                                 │  │
   │  │  From: John Doe                                 │  │
   │  │  Type: Annual Leave                             │  │
   │  │  Dates: 1-5 March 2026 (5 days)                 │  │
   │  │  Reason: Family vacation                        │  │
   │  │                                                 │  │
   │  │  [✅ Approve]  [❌ Reject]                       │  │
   │  └─────────────────────────────────────────────────┘  │
   └───────────────────────┬───────────────────────────────┘
                           │
                           │ Manager taps "Approve"
                           ▼

STEP 4: Lark Fires Webhook
───────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  Lark Platform                                        │
   │                                                       │
   │  POST https://api.belive.com/webhooks/lark/approval   │
   │                                                       │
   │  Headers:                                             │
   │    X-Lark-Request-Timestamp: 1675843200               │
   │    X-Lark-Request-Nonce: abc123                       │
   │    X-Lark-Signature: sha256_hmac(...)                 │
   │                                                       │
   │  Body:                                                │
   │  {                                                    │
   │    "header": {                                        │
   │      "event_type": "approval_instance.approved"       │
   │    },                                                 │
   │    "event": {                                         │
   │      "instance_code": "abc123",                       │
   │      "operator_id": {                                 │
   │        "user_id": "ou_manager_xxx"                    │
   │      },                                               │
   │      "update_time": "1675843200",                     │
   │      "form": [                                        │
   │        { "id": "leave_id", "value": "456" },          │
   │        ...                                            │
   │      ]                                                │
   │    }                                                  │
   │  }                                                    │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼

STEP 5: Laravel Webhook Handler (Anti-Corruption Layer)
────────────────────────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  LarkWebhookController                                │
   │                                                       │
   │  1. Verify Signature                                  │
   │     ┌───────────────────────────────────────────┐     │
   │     │ $signature = hash_hmac(                  │     │
   │     │   'sha256',                              │     │
   │     │   $timestamp . $nonce . $token . $body,  │     │
   │     │   $token                                 │     │
   │     │ );                                       │     │
   │     │                                          │     │
   │     │ if (!hash_equals($signature, $header)) { │     │
   │     │   return 401 Unauthorized                │     │
   │     │ }                                        │     │
   │     │ ✅ Signature valid                        │     │
   │     └───────────────────────────────────────────┘     │
   │                                                       │
   │  2. Delegate to Handler                               │
   │     $handler->handle($request->all())                 │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  LarkLeaveWebhookHandler                              │
   │                                                       │
   │  match ($eventType) {                                 │
   │    'approval_instance.approved' =>                    │
   │      $this->handleApprovalApproved($payload)          │
   │  }                                                    │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  LarkApprovalTransformer (Anti-Corruption)            │
   │                                                       │
   │  Transform Lark's structure → Our domain model        │
   │                                                       │
   │  Lark Payload:                    Domain Event:       │
   │  {                                                    │
   │    "operator_id": "ou_xxx",  ───►  approverId: 45     │
   │    "instance_code": "abc",                            │
   │    "form": [                 ───►  leaveId: 456       │
   │      {"id": "leave_id",                               │
   │       "value": "456"}                                 │
   │    ]                                                  │
   │  }                                                    │
   │                                                       │
   │  return new LeaveApprovalReceived(                    │
   │    leaveId: 456,                                      │
   │    approverId: 45,                                    │
   │    decision: ApprovalDecision::APPROVED,              │
   │    approvedAt: Carbon::createFromTimestamp(...),      │
   │    metadata: ['lark_instance' => 'abc123']            │
   │  );                                                   │
   └───────────────────────┬───────────────────────────────┘
                           │
                           │ event() dispatches
                           ▼

STEP 6: Domain Event Listener Processes
────────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  ProcessLeaveApproval (Domain Listener)               │
   │                                                       │
   │  public function handle(LeaveApprovalReceived $event) │
   │  {                                                    │
   │    $this->approvalService->processApproval(           │
   │      leaveId: $event->leaveId,                        │
   │      approverId: $event->approverId,                  │
   │      decision: $event->decision                       │
   │    );                                                 │
   │  }                                                    │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  LeaveApprovalService (Domain)                        │
   │                                                       │
   │  DB::transaction(function() {                         │
   │    // 1. Update leave status                          │
   │    $leave->update([                                   │
   │      'status' => 'approved',                          │
   │      'approved_by' => 45,                             │
   │      'approved_at' => now()                           │
   │    ]);                                                │
   │                                                       │
   │    // 2. Deduct from balance                          │
   │    $user->deductLeaveBalance('annual', 5);            │
   │    // User now has: 12 - 5 = 7 days left              │
   │                                                       │
   │    // 3. Dispatch event                               │
   │    event(new LeaveApproved($leave));                  │
   │  });                                                  │
   └───────────────────────┬───────────────────────────────┘
                           │
                           │ Realtime update fires
                           ▼

STEP 7: Employee Sees Update Instantly
───────────────────────────────────────

   ┌───────────────────────────────────────────────────────┐
   │  Next.js Supabase Realtime                            │
   │                                                       │
   │  supabase                                             │
   │    .channel('leave-changes')                          │
   │    .on('postgres_changes', {                          │
   │      event: 'UPDATE',                                 │
   │      table: 'leaves',                                 │
   │      filter: 'id=eq.456'                              │
   │    }, (payload) => {                                  │
   │      if (payload.new.status === 'approved') {         │
   │        toast.success('Leave approved! 🎉')            │
   │        queryClient.invalidateQueries(['leaves'])      │
   │      }                                                │
   │    })                                                 │
   └───────────────────────┬───────────────────────────────┘
                           │
                           ▼
   ┌───────────────────────────────────────────────────────┐
   │  Employee's Screen                                    │
   │                                                       │
   │  ╔══════════════════════════════════════════════════╗ │
   │  ║  🎉 Leave Approved!                               ║ │
   │  ║                                                  ║ │
   │  ║  Your manager approved your leave request.       ║ │
   │  ║  Dates: 1-5 March 2026                           ║ │
   │  ║                                                  ║ │
   │  ║  Remaining annual leave: 7 days                  ║ │
   │  ╚══════════════════════════════════════════════════╝ │
   └───────────────────────────────────────────────────────┘

TOTAL TIME: Manager clicks "Approve" → Employee sees update
            ~500-800ms

Key Patterns Demonstrated:
✅ Adapter Pattern (vendor independence)
✅ Anti-Corruption Layer (webhook transformation)
✅ Domain Events (clean coordination)
✅ Realtime updates (instant UX)
✅ Audit trail (automatic via Spatie)
```

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)

**Goal:** Set up core infrastructure and prove authentication works

#### Day 1-2: Laravel Setup
```bash
# Create project
composer create-project laravel/laravel belive-api
cd belive-api

# Install dependencies
composer require saeedvir/supabase
composer require spatie/laravel-activitylog
composer require --dev laravel/boost
composer require --dev barryvdh/laravel-ide-helper

# Note: Laravel Sanctum and Spatie Permissions are NOT used.
# Authentication/authorization handled by Supabase (JWT + RLS).

# Generate IDE helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

#### Day 3-4: Supabase Configuration
```bash
# .env configuration
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_SECRET=your-service-role-key
SUPABASE_JWT_SECRET=your-jwt-secret-32-chars-min

# Test connection
php artisan tinker
>>> $supabase = new \Supabase\Supabase();
>>> $result = $supabase->from('users')->select('*')->limit(1)->execute();
>>> dd($result);
```

#### Day 5-7: Lark OAuth Integration
```php
// routes/api.php
Route::post('/auth/lark/callback', [AuthController::class, 'larkCallback']);

// app/Http/Controllers/Auth/AuthController.php
public function larkCallback(Request $request)
{
    // 1. Exchange code for access token
    $tokenResponse = Http::post('https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal', [
        'app_id' => config('services.lark.app_id'),
        'app_secret' => config('services.lark.app_secret'),
    ]);
    
    $accessToken = $tokenResponse->json('tenant_access_token');
    
    // 2. Get user info
    $userResponse = Http::withToken($accessToken)
        ->get('https://open.larksuite.com/open-apis/authen/v1/access_token', [
            'grant_type' => 'authorization_code',
            'code' => $request->code,
        ]);
    
    $larkUser = $userResponse->json('data');
    
    // 3. Find or create user
    $user = User::firstOrCreate(
        ['lark_user_id' => $larkUser['user_id']],
        [
            'email' => $larkUser['email'],
            'name' => $larkUser['name'],
        ]
    );
    
    // 4. Generate Supabase JWT token
    // Laravel is responsible for JWT generation after successful authentication
    // This JWT will be validated by Next.js BFF and used by Supabase RLS
    $supabaseToken = app(SupabaseJwtService::class)->generateToken($user);
    
    // Note: No Sanctum token needed. Next.js BFF will use Supabase JWT
    // and pass user identity to Laravel via X-User-ID header.
    
    return response()->json([
        'user' => $user,
        'supabase_token' => $supabaseToken,
    ]);
}
```

#### Day 8-10: Next.js Setup
```bash
npx create-next-app@latest belive-frontend --typescript --tailwind --app
cd belive-frontend

npm install @supabase/supabase-js
npm install @tanstack/react-query zustand
npm install react-hook-form @hookform/resolvers zod
npx shadcn-ui@latest init
npx shadcn-ui@latest add button form input
```

**Success Criteria:**
- ✅ User can log in via Lark OAuth
- ✅ Laravel issues Supabase JWT token
- ✅ Next.js BFF validates Supabase JWT and calls Laravel with X-User-ID header
- ✅ Laravel TrustedBffMiddleware validates internal API calls

---

### Phase 2: Attendance Module (Week 3-4)

**Goal:** Build complete vertical slice - prove GPS + Realtime works

#### Week 3: Backend

**Day 1-2: Database Schema**
```sql
-- Create table in Supabase
CREATE TABLE attendance (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    clocked_at TIMESTAMP NOT NULL,
    clocked_out_at TIMESTAMP,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    device_accuracy INT,
    wifi_ssid VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Enable RLS
ALTER TABLE attendance ENABLE ROW LEVEL SECURITY;

-- Policy: Users see own records
CREATE POLICY "users_see_own_attendance"
ON attendance FOR SELECT
USING (auth.uid()::text = user_id::text);

-- Policy: Users can insert own records
CREATE POLICY "users_insert_own_attendance"
ON attendance FOR INSERT
WITH CHECK (auth.uid()::text = user_id::text);

-- Index for performance
CREATE INDEX idx_attendance_user_date ON attendance(user_id, clocked_at);
```

**Day 3-5: Laravel Implementation**
```php
// app/Modules/Attendance/Domain/Rules/AttendanceRules.php
// Note: This is a business rule validator, NOT a Laravel authorization policy
class AttendanceRules
{
    private const OFFICE_LAT = 3.1390;  // Update with your office
    private const OFFICE_LNG = 101.6869;
    private const GEOFENCE_RADIUS_METERS = 200;
    
    public function isWithinGeofence(float $lat, float $lng): bool
    {
        $distance = $this->haversineDistance(
            self::OFFICE_LAT,
            self::OFFICE_LNG,
            $lat,
            $lng
        );
        
        return $distance <= self::GEOFENCE_RADIUS_METERS;
    }
    
    public function hasAlreadyClockedIn(int $userId): bool
    {
        return Attendance::where('user_id', $userId)
            ->whereDate('clocked_at', today())
            ->whereNull('clocked_out_at')
            ->exists();
    }
    
    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000; // meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}

// app/Http/Controllers/Api/AttendanceController.php
class AttendanceController extends Controller
{
    public function clockIn(Request $request, ClockInHandler $handler)
    {
        // User identity comes from TrustedBffMiddleware (X-User-ID header)
        // No authorization check needed - Supabase RLS handles access control
        
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'wifi_ssid' => 'nullable|string',
        ]);
        
        $command = new ClockInCommand(
            userId: $request->header('X-User-ID'), // From middleware
            latitude: $validated['latitude'],
            longitude: $validated['longitude'],
            wifiSsid: $validated['wifi_ssid'] ?? null,
            clockedAt: now()
        );
        
        $handler->handle($command);
        
        return response()->json(['success' => true]);
    }
}
```

#### Week 4: Frontend

**Day 1-3: Next.js Clock-In UI**
```typescript
// app/attendance/page.tsx
'use client'

import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { useToast } from '@/hooks/use-toast'

export default function AttendancePage() {
  const [loading, setLoading] = useState(false)
  const { toast } = useToast()
  const queryClient = useQueryClient()
  
  const handleClockIn = async () => {
    setLoading(true)
    
    try {
      // Get location from Lark SDK
      const location = await window.tt.getLocation({ type: 'gcj02' })
      const wifi = await window.tt.getWifiStatus()
      
      // Send to Laravel
      const response = await fetch('/api/attendance/clock-in', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('api_token')}`,
        },
        body: JSON.stringify({
          latitude: location.latitude,
          longitude: location.longitude,
          wifi_ssid: wifi.ssid,
        }),
      })
      
      if (!response.ok) throw new Error('Clock-in failed')
      
      toast({ title: 'Clocked in successfully!' })
      queryClient.invalidateQueries({ queryKey: ['attendance'] })
    } catch (error) {
      toast({
        title: 'Clock-in failed',
        description: error.message,
        variant: 'destructive',
      })
    } finally {
      setLoading(false)
    }
  }
  
  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Attendance</h1>
      <Button onClick={handleClockIn} disabled={loading}>
        {loading ? 'Clocking in...' : 'Clock In'}
      </Button>
    </div>
  )
}
```

**Day 4-5: Realtime Updates**
```typescript
// hooks/useAttendanceRealtime.ts
import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { createSupabaseClient } from '@/lib/supabase-client'

export function useAttendanceRealtime(userId: number) {
  const queryClient = useQueryClient()
  
  useEffect(() => {
    const supabaseToken = localStorage.getItem('supabase_token')
    if (!supabaseToken) return
    
    const supabase = createSupabaseClient(supabaseToken)
    
    const channel = supabase
      .channel('attendance-changes')
      .on(
        'postgres_changes',
        {
          event: 'INSERT',
          schema: 'public',
          table: 'attendance',
          filter: `user_id=eq.${userId}`,
        },
        (payload) => {
          queryClient.setQueryData(['attendance'], (old: any) => [
            payload.new,
            ...(old || []),
          ])
        }
      )
      .subscribe()
    
    return () => {
      supabase.removeChannel(channel)
    }
  }, [userId, queryClient])
}
```

**Success Criteria:**
- ✅ User can clock in using Lark GPS
- ✅ Laravel validates geofence
- ✅ Data saved to Supabase
- ✅ UI updates in realtime
- ✅ Activity log records who clocked in when

---

### Phase 3: Leave Module (Week 5-7)

**Goal:** Implement approval workflow with Lark

**(Implementation details similar to Phase 2, but focusing on:**
- Leave submission form
- Lark Approval API integration
- Webhook handling
- Domain events for approval processing

---

### Phase 4: Claims Module + Polish (Week 8-10)

**Goal:** Add AI OCR and finalize system

**(Implementation details including:**
- AI OCR for receipt scanning
- Multi-step approval workflows
- HR dashboards
- Audit trail UI

---

## Module Implementation Guides

### Attendance Module - Complete Code

*[Full implementation code for Attendance module with all files]*

### Leave Module - Complete Code

*[Full implementation code for Leave module with all files]*

### Claims Module - Complete Code

*[Full implementation code for Claims module with all files]*

---

**Document Version:** 2.0  
**Last Updated:** February 6, 2026  
**Status:** Production Implementation Guide