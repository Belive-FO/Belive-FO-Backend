# Auth change spec: httpOnly Bearer + Next.js proxy

This document describes the **planned** changes for moving to **httpOnly Bearer token with a Next.js API proxy** (thin BFF). The backend already supports Bearer tokens (Sanctum API tokens); no backend auth changes are required. This spec covers **Next.js** and **documentation**. **ARCHITECTURE.md** and **.cursorrules** have been updated to reflect the new authentication flow; IMPLEMENTATION.md, DEVELOPMENT.md, and README.md remain to be updated when the Next.js implementation is done.

---

## 1. Summary of the new flow

| Aspect | Current (per docs) | New (proposed) |
|--------|--------------------|-----------------|
| **Flow** | Browser → Laravel (direct) | Browser → Next.js → Laravel (proxy) |
| **Token storage** | Session cookie (SPA) or client-side Bearer | httpOnly cookie (Bearer value); JS never sees token |
| **Who talks to Laravel** | Browser (with cookie or `Authorization` header) | Next.js server only (server-to-server with `Authorization: Bearer`) |
| **CSRF** | Required for SPA/session (GET `/sanctum/csrf-cookie`, `X-XSRF-TOKEN`) | Not needed for API (no cookie auth from browser to Laravel) |
| **Security** | Session or client-visible token | Token only in httpOnly cookie → XSS cannot steal it |

**Rationale:** httpOnly Bearer gives stronger protection (no token in JS), and the proxy hop (Next.js → Laravel) is server-to-server so latency impact is small. Backend stays as-is and continues to accept `Authorization: Bearer <token>`.

---

## 2. Next.js changes

All API behavior (proxy, login, logout, auth/me) is implemented with the **App Router** using **Route Handlers** in **`route.ts`** files, following [Next.js Route Handlers](https://nextjs.org/docs/app/building-your-application/routing/route-handlers) best practices. Do not use the legacy Pages Router (`pages/api/`) for these endpoints.

### 2.1 High-level behavior

- **Login / Lark OAuth callback:**  
  - Browser hits **Next.js** (e.g. API route or server action).  
  - Next.js calls Laravel (e.g. `POST /api/auth/login` or Laravel’s Lark callback URL).  
  - Laravel returns `{ token: "1|..." }` (or equivalent).  
  - Next.js sets an **httpOnly cookie** with the token (e.g. name `belive_token` or `auth_token`), then returns success (or redirect).  
  - Response body **must not** include the raw token for the client (or strip it before sending to browser).

- **All other API calls:**  
  - Browser calls **Next.js** API Route Handlers (App Router). The proxy is implemented as **`route.ts`** per Next.js App Router conventions (see [Next.js Route Handlers](https://nextjs.org/docs/app/building-your-application/routing/route-handlers)).  
  - Example: `GET /api/proxy/attendance/clock-in` or a catch-all `app/api/proxy/[...path]/route.ts`.  
  - Next.js reads the token from the **httpOnly cookie** (only on server).  
  - Next.js calls Laravel with `Authorization: Bearer <token>`.  
  - Next.js returns Laravel’s response to the browser (or forwards status/body).

- **Logout:**  
  - Browser calls Next.js logout route.  
  - Next.js calls Laravel `POST /api/auth/logout` with `Authorization: Bearer <token>` (token from cookie), then clears the httpOnly cookie and returns (or redirects to `/login`).

- **No client-side token:**  
  - Remove any Zustand/localStorage/sessionStorage store for the Laravel token.  
  - Do not send `Authorization` from the browser to Laravel; only Next.js server does.

### 2.2 Concrete Next.js implementation checklist

- **Environment**
  - Add `NEXT_PUBLIC_LARAVEL_API_URL` only if the **browser** needs the Laravel base URL for something non-auth (e.g. redirects).  
  - Add server-only `LARAVEL_API_URL` (or `BACKEND_URL`) for the Next.js server to call Laravel (used in proxy and login callback).

- **Cookie**
  - **Name:** e.g. `belive_auth_token` or `auth_token` (same name everywhere).
  - **Options:** `httpOnly: true`, `secure` in production, `sameSite: 'lax'` (or `strict` if no cross-site redirects), `path: '/'`, `maxAge` consistent with token lifetime if you set one.
  - **Value:** The Sanctum API token string only (e.g. `1|abc...`). No JSON wrapper unless you explicitly document it.

- **Login (email/password if applicable)**
  - Next.js Route Handler (e.g. `app/api/auth/login/route.ts` with `POST`) receives `email`/`password`.  
  - Server calls Laravel `POST ${LARAVEL_API_URL}/api/auth/login` with JSON body (no cookie, no CSRF).  
  - On success, Laravel returns `token` (or `data.token`); Next.js sets httpOnly cookie with that value and returns `{ user, ... }` **without** `token` in the body.  
  - On failure, forward Laravel status/body.

- **Lark OAuth**
  - **Redirect to Lark:** Either browser redirects to Laravel’s Lark auth URL, or Next.js returns a redirect to that URL. (No change to Laravel’s Lark routes.)
  - **Callback:**  
    - **Option A:** Laravel handles callback, creates token, then **redirects to Next.js** with token in **query** (e.g. `https://app.example.com/auth/callback?token=1|...`). Next.js API route or page reads `token` from query, sets httpOnly cookie, then redirects to app (and removes `token` from URL if possible).  
    - **Option B:** Callback goes to Next.js first; Next.js forwards `code` to Laravel (server-to-server), Laravel returns token in JSON; Next.js sets cookie and redirects.  
  - Whichever option: **token must never be left in browser history or client-visible response**. Prefer setting cookie and redirecting so the URL is replaced. Callback handling can be a Route Handler (e.g. `app/api/auth/callback/route.ts`) or a server component/action that sets the cookie.

- **API proxy (App Router, `route.ts`)**
  - **Implementation:** Use the **App Router** and **Route Handlers** as per [Next.js documentation](https://nextjs.org/docs/app/building-your-application/routing/route-handlers). The proxy is a **`route.ts`** file (not the legacy Pages Router `pages/api/`).  
  - **Path:** e.g. `app/api/proxy/[...path]/route.ts` so that `/api/proxy/attendance/clock-in` forwards to Laravel `/api/attendance/clock-in`. Export the appropriate HTTP method handlers (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`, etc.) that forward to Laravel.
  - **Read cookie:** In the Route Handler, read the httpOnly auth cookie; if missing, return 401 (or 302 to login).
  - **Forward request:** Method, path, query, and body from client request → Laravel. Strip or ignore client-sent `Authorization` and cookie when calling Laravel; always set `Authorization: Bearer <cookie_token>`.
  - **Forward response:** Status, headers (filter out Laravel-specific ones like `Set-Cookie` if not needed), and body back to the client.
  - **CORS:** Not needed for same-origin browser → Next.js. Next.js → Laravel is server-side.

- **Frontend data fetching**
  - All Laravel API calls go to **Next.js proxy** (e.g. `fetch('/api/proxy/attendance/clock-in', { method: 'POST', body: ... })`) with `credentials: 'include'` so the auth cookie is sent to Next.js. No `Authorization` header from the client; no direct Laravel URL in the client for API.
  - Remove any previous usage of `GET /sanctum/csrf-cookie`, `X-XSRF-TOKEN`, and direct `Authorization: Bearer` from the client to Laravel.

- **Logout**
  - Next.js Route Handler (e.g. `app/api/auth/logout/route.ts` with `POST`) reads token from cookie, calls Laravel `POST /api/auth/logout` with `Authorization: Bearer <token>`, then clears the auth cookie and returns (or redirects to `/login`).

- **Auth state for UI**
  - Use a **session endpoint** if needed: e.g. `app/api/auth/me/route.ts` with `GET` that reads cookie, calls Laravel’s user/me endpoint with Bearer, returns user or 401. Client only calls Next.js; no token in client memory.

### 2.3 What to remove or avoid on the frontend

- Do **not** call `GET /sanctum/csrf-cookie` for API requests.
- Do **not** send `X-XSRF-TOKEN` for API requests.
- Do **not** use `credentials: 'include'` for **direct** browser → Laravel API calls (all API goes through Next.js).
- Do **not** store the Laravel token in Zustand, localStorage, or sessionStorage.
- Do **not** send `Authorization: Bearer` from the browser; only the Next.js server sends it to Laravel.

---

## 3. Documentation changes

**Status:** ARCHITECTURE.md and .cursorrules have been updated. Other files below are a checklist for future updates when the Next.js implementation is done.

### 3.1 `backend/docs/ARCHITECTURE.md` — ✅ Updated

- **ADR-003** – Note updated to Sanctum API tokens (Bearer) + thin BFF.
- **ADR-004 (Laravel-First)**  
  - Update “After” flow from “Next.js calls Laravel with session cookie” to “Browser → Next.js (proxy); Next.js calls Laravel with `Authorization: Bearer` (token from httpOnly cookie).”  
  - In “Removed Components”, change “❌ BFF pattern” to reflect that a **proxy** is now used for auth (token in httpOnly cookie, no client-side token). Optionally add a short ADR or note: “Proxy pattern for httpOnly Bearer” (security and single backend URL).

- **Request Flow Example: Clock-In**  
  - Replace “Next.js calls Laravel” with “Browser → Next.js API route (with cookie) → Next.js server calls Laravel with `Authorization: Bearer <token>` (token from httpOnly cookie).”  
  - Remove steps about GET `/sanctum/csrf-cookie` and session cookie for API.

- **Authority Boundaries / Authentication**  
  - State that the **browser** never sends the Bearer token; Next.js server reads it from an httpOnly cookie and sends it to Laravel.  
  - Keep “Laravel Sanctum” as the authority; clarify “Sanctum API tokens (Bearer), validated by Laravel; token stored in httpOnly cookie, forwarded by Next.js proxy.”

- **Responsibility Matrix**  
  - Authentication row: e.g. “Laravel Sanctum (API tokens); token in httpOnly cookie; Next.js proxy adds `Authorization: Bearer`.”

- **Alternatives Considered**  
  - Revise “Token-Based Sanctum: Rejected” or add a new line: “Token-Based Sanctum with httpOnly cookie + proxy: Accepted for XSS protection; Next.js proxies requests and sends Bearer token from cookie.”

### 3.2 `backend/docs/IMPLEMENTATION.md`

- Update any diagrams that show “Browser → Laravel” for API to “Browser → Next.js → Laravel”.
- Replace “Session-based (SPA mode)” / “Session cookie” with “Bearer token in httpOnly cookie; Next.js proxy.”
- In Clock-In (or similar) flow, show:  
  - Headers from browser to Next.js: cookie only (no `Authorization`).  
  - Headers from Next.js to Laravel: `Authorization: Bearer <token>`.
- Remove or adjust references to CSRF for API calls.

### 3.3 `backend/docs/DEVELOPMENT.md`

- **Sanctum / auth testing**  
  - If it describes “session cookie” or “CSRF cookie” for the frontend, update to “httpOnly cookie containing Bearer token; requests go through Next.js proxy.”  
  - Keep backend tests as-is (Sanctum still validates Bearer token).

### 3.4 `backend/docs/README.md`

- Short “Authentication” line: e.g. “Lark OAuth → Laravel issues token → Next.js sets httpOnly cookie and proxies API with Bearer.”

### 3.5 `backend/README.md`

- If it mentions Sanctum or auth, align with “Bearer token; Next.js holds token in httpOnly cookie and proxies to Laravel.”

### 3.6 `.cursorrules` (project root) — ✅ Updated

All of the following have been applied to `.cursorrules`.

- **Laravel-First Authentication & Authorization**  
  - Replace “SPA mode” / “session cookies” with: “Sanctum API tokens (Bearer). Token stored in httpOnly cookie; Next.js proxy sends `Authorization: Bearer` to Laravel.”

- **Request Flow Pattern**  
  - Update to:  
    - “User → Lark OAuth → Laravel (validates, returns token) → Next.js sets httpOnly cookie (and optionally redirects).”  
    - “User → Next.js (sends cookie) → Next.js proxy → Laravel (Sanctum validates Bearer, Policy checks authorization).”

- **BFF / proxy**  
  - Change “DO NOT use BFF pattern” to something like: “Use a **thin proxy** only: Next.js App Router Route Handlers (`route.ts`) forward requests to Laravel with Bearer token from httpOnly cookie; no custom auth middleware or X-User-ID trust on Laravel.”

### 3.7 Other docs

- Search for “session”, “CSRF”, “sanctum/csrf-cookie”, “SPA mode”, “session cookie” in `backend/docs`, `AGENTS.md`, and any frontend docs; update to match the new flow and remove obsolete CSRF/session steps for API.

---

## 4. Backend (no change required)

- Laravel already issues Sanctum API tokens (e.g. on login/OAuth) and validates `Authorization: Bearer <token>`.
- No new Laravel routes or middleware are required for this spec.
- Optional: ensure login/OAuth responses include `token` in JSON (or that the redirect-to-Next.js flow includes the token in query so Next.js can set the cookie); this may already be the case.

---

## 5. Checklist before closing this spec

- [ ] Next.js: httpOnly cookie name and options decided and documented.
- [ ] Next.js: Login (and Lark callback) set cookie and never expose token to client.
- [ ] Next.js: All API calls go through proxy; proxy reads cookie and sends Bearer to Laravel.
- [ ] Next.js: Logout clears cookie and calls Laravel revoke.
- [ ] Next.js: No client-side token storage; no CSRF for API.
- [x] Docs: ARCHITECTURE.md and .cursorrules updated with new authentication flow (thin BFF, httpOnly Bearer).
- [ ] Docs: IMPLEMENTATION.md, DEVELOPMENT.md, README.md updated when Next.js implementation is done (see §3.2–3.5).
- [ ] Backend: Confirm login/OAuth response shape (token in JSON or redirect query) matches Next.js expectations.
