# TrustedBffMiddleware Usage Guide

## Overview

`TrustedBffMiddleware` validates that API requests come from the trusted Next.js BFF (Backend for Frontend). In a Supabase-first architecture, this middleware ensures Laravel only accepts requests from authenticated Next.js instances.

## How It Works

### Request Flow

```
1. User authenticates → Supabase JWT issued
2. Next.js BFF validates Supabase JWT
3. Next.js extracts user ID from JWT
4. Next.js calls Laravel with:
   - X-User-ID: 123 (from JWT)
   - X-Internal-Key: [shared secret]
5. TrustedBffMiddleware validates:
   - X-Internal-Key matches config('services.bff.secret')
   - X-User-ID is present and numeric
6. Middleware sets user context
7. Request proceeds to controller
```

### Security Model

- **Shared Secret**: Only Next.js instances with the correct `BFF_INTERNAL_SECRET` can call Laravel
- **No Re-authentication**: Laravel trusts the BFF's identity assertion (BFF already validated Supabase JWT)
- **User Context**: User ID is extracted from header and made available to Laravel

## Configuration

### Environment Variables

Add to `.env`:

```env
BFF_INTERNAL_SECRET=your-super-secret-key-at-least-32-characters-long
```

**Important**: Use a strong, randomly generated secret. This should be different from your Supabase secrets.

### Registering the Middleware

The middleware is already registered in `bootstrap/app.php` with the alias `trusted.bff`.

## Usage

### Apply to Routes

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

// Apply to specific routes
Route::middleware('trusted.bff')->group(function () {
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('/leave/submit', [LeaveController::class, 'submit']);
});

// Or apply to all API routes
Route::middleware(['trusted.bff'])->prefix('internal')->group(function () {
    // All routes here require trusted BFF
});
```

### Accessing User ID in Controllers

```php
// Method 1: From request header (direct)
$userId = $request->header('X-User-ID');

// Method 2: From merged request data
$userId = $request->input('user_id');

// Method 3: If you set auth()->setUser() in middleware
$userId = auth()->id();
```

### Example Controller

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function clockIn(Request $request)
    {
        // User ID comes from TrustedBffMiddleware
        $userId = $request->header('X-User-ID');
        
        // No authorization check needed - Supabase RLS handles access control
        // Just validate business rules
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        
        // Execute business logic...
        
        return response()->json(['success' => true]);
    }
}
```

## Next.js BFF Implementation

Your Next.js BFF should send requests like this:

```typescript
// lib/api-client.ts
const BFF_INTERNAL_SECRET = process.env.BFF_INTERNAL_SECRET;

export async function callLaravelAPI(
  endpoint: string,
  options: RequestInit = {}
) {
  // Get Supabase JWT from session
  const supabaseToken = await getSupabaseToken();
  
  // Extract user ID from JWT (you'll need a JWT decoder)
  const userId = extractUserIdFromJWT(supabaseToken);
  
  // Call Laravel with required headers
  const response = await fetch(`https://api.belive.com${endpoint}`, {
    ...options,
    headers: {
      ...options.headers,
      'X-User-ID': userId.toString(),
      'X-Internal-Key': BFF_INTERNAL_SECRET,
      'Content-Type': 'application/json',
    },
  });
  
  return response;
}
```

## Error Responses

The middleware will return these errors if validation fails:

- **403 Forbidden**: Missing or invalid `X-Internal-Key` header
- **400 Bad Request**: Missing or invalid `X-User-ID` header

## Security Considerations

1. **Never expose `BFF_INTERNAL_SECRET`** to the client-side code
2. **Use HTTPS** for all API calls
3. **Rotate the secret** periodically
4. **Monitor for unauthorized access attempts**
5. **Consider IP whitelisting** for additional security (optional)

## Testing

### Unit Test Example

```php
use App\Http\Middleware\TrustedBffMiddleware;
use Illuminate\Http\Request;

class TrustedBffMiddlewareTest extends TestCase
{
    public function test_validates_internal_key()
    {
        $middleware = new TrustedBffMiddleware();
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_INTERNAL_KEY' => 'wrong-secret',
            'HTTP_X_USER_ID' => '123',
        ]);
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        
        $middleware->handle($request, function ($req) {
            return response('ok');
        });
    }
    
    public function test_sets_user_context()
    {
        config(['services.bff.secret' => 'test-secret']);
        
        $middleware = new TrustedBffMiddleware();
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_INTERNAL_KEY' => 'test-secret',
            'HTTP_X_USER_ID' => '123',
        ]);
        
        $response = $middleware->handle($request, function ($req) {
            $this->assertEquals(123, $req->input('user_id'));
            return response('ok');
        });
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## Migration from Sanctum

If you're migrating from Laravel Sanctum:

**Before:**
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
});

// In controller
$userId = auth()->id();
```

**After:**
```php
Route::middleware('trusted.bff')->group(function () {
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
});

// In controller
$userId = $request->header('X-User-ID');
```


