# FlowOffice Backend — Done / Implementation Log

Completed work and implementation summaries for the BeLive FlowOffice backend.

---

## 1. Multi-Angle Face Registration & Verification

**Overview:** Upgraded face registration from a single photo to 3 angles (Front, Left, Right) per user and wired the flow to Human API and the frontend.

### Backend (Laravel)

- **Migration** `2026_03_03_000001_add_face_front_left_right_url_to_users_table.php`
  - Added nullable columns: `face_front_url`, `face_left_url`, `face_right_url` on `users`
  - Applied to Supabase
- **User model** — Removed `face_photo_url` from `$fillable`; added the three new URL fields.
- **ProfileController**
  - `uploadFacePhoto`: accepts `position` (`front` | `left` | `right`), fixed filenames per position, deletes old file before saving, writes to the correct column.
  - `updateMe`: accepts `face_front_url`, `face_left_url`, `face_right_url` (snake_case in request).
  - `buildProfile`: returns the three face URL fields instead of `facePhotoUrl`.

### Human API

- Env vars for `SUPABASE_FACE_FRONT_URL_COLUMN`, `_LEFT`, `_RIGHT`; reads all 3 columns and returns best match score.

### Frontend

- Profile type and UI updated for 3 uploads; FormData includes `position`; face verify unchanged (Human API called directly).

### API contract

| Endpoint | Change |
|----------|--------|
| `POST /profile/face-photo` | Body must include `position`: `"front"` \| `"left"` \| `"right"` |
| `GET /profile/me` | Returns `faceFrontUrl`, `faceLeftUrl`, `faceRightUrl` |
| `PUT /profile/me` | Accepts `face_front_url`, `face_left_url`, `face_right_url` |
| `POST /face/verify` | Unchanged (mock; frontend calls Human API directly) |

---

## 2. Clock-in Flow Debugging & Fixes

**Overview:** Fixed clock-in not persisting to DB; root cause spanned DB, validation, and frontend.

### Fixes

1. **snake_case / camelCase** — Frontend sends snake_case; backend was expecting camelCase.
   - `AttendanceController::store()`: `office_id`, `photo_url`.
   - `ProfileController::updateMe()`: `full_name`, `avatar_url`, `face_front_url`, `face_left_url`, `face_right_url`.
2. **photo_url size** — `attendance_logs.photo_url` was `VARCHAR(255)`; base64 selfies are 50k–200k+ chars.
   - Migration `2026_03_06_000001_change_attendance_logs_photo_url_to_text.php` → column set to `TEXT`.
3. **Selfie upload** — New `POST /attendance/upload-photo` (FormData, field `photo`, max 5 MB).
   - Stores under `attendance-selfies/{userId}/{uuid}.jpg`; returns `{ data: { url } }`.
   - Frontend: upload first → use returned URL as `photo_url` in clock-in payload.
4. **Content-Type** — Frontend was setting `Content-Type: multipart/form-data` without boundary → empty body → 422. Fixed by letting Axios set the header.

---

## 3. Database Cleanup — Drop face_photo_url

- Confirmed no code still reads/writes `face_photo_url`; Human API uses the new columns.
- Migration `2026_03_06_000002_drop_face_photo_url_from_users_table.php` — column removed on Supabase.

---

## 4. Claims Module & Geocoding

**Overview:** Full Claims CRUD, approvals, analytics, and Google Maps geocode/reverse-geocode endpoints.

### Backend — Claims

- **Models & migrations:** Claim, ClaimCategory, ClaimMileageDetail, ClaimAttachment, ClaimStatusLog; 5 migrations applied.
- **Permissions (Spatie):** `claims.view-own`, `claims.view-team`, `claims.create`, `claims.approve`, `claims.reject`; roles (employee, manager, hr_admin, super_admin) created with guard `sanctum`. `AUTH_GUARD=sanctum` in `.env`. Seeder: `RolesAndPermissionsSeeder`.
- **Controllers:** ClaimController (CRUD), ClaimApprovalController (approve/reject/mark-paid/all), ClaimAttachmentController, ClaimStatsController (stats, monthly-spend, categories, mileage-rate, calculate-distance).
- **Validation:** `category_id` optional (nullable on `claims`); mileage `from_location`/`to_location` max 2000 chars; DB columns for locations as TEXT. MileageAmountMatchesCalculation rule.
- **Analytics:**
  - `GET /api/claim-categories` — live `spent` per category from approved/paid claims (user-scoped); `spent` as number.
  - `GET /api/claims/monthly-spend` — last 6 months, `{ month, amount }`, 0-filled; default 6 months.

### Backend — Geocoding

- **GeocodeService:** `geocode(address)` → lat/lng; `reverseGeocode(lat, lng)` → location name (address_components + fallback to formatted_address).
- **Endpoints:** `POST /api/geocode` (body: `address`), `POST /api/reverse-geocode` (body: `lat`, `lng`). Both use `GOOGLE_PLACES_API_KEY`; require **Geocoding API** enabled in Google Cloud (same project as Distance Matrix).

### API routes added

- Claims: `GET/POST /api/claims`, `GET/PUT/DELETE /api/claims/{claim}`, `GET /api/claims/stats`, `GET /api/claims/monthly-spend`, `GET /api/claims/mileage-rate`, `POST /api/claims/calculate-distance`, `GET /api/claim-categories`, `GET /api/claims/all` (manager+), `PATCH .../approve|reject|mark-paid`, attachment routes.
- Geocode: `POST /api/geocode`, `POST /api/reverse-geocode`.










### Frontend — Claims

- **Hooks & data**
  - Refactored claims to use new hooks for: claims list, categories, stats, monthly spend, mileage rate.
  - Replaced mock data with TanStack Query and real API calls.
- **Charts & components**
  - `BudgetUtilization` and `ClaimsByCategoryChart` use dynamic categories (and related data) from the API.
  - `MonthlySpendChart` uses dynamic `monthlySpend` from the API.
- **MileageClaimDialog**
  - Removed Category field; Trip Date is full-width.
  - Distance from backend Google Maps: `POST /api/claims/calculate-distance` (no OpenStreetMap).
  - Distance editable; amount/rate read-only; distance rounded up (e.g. 32.75 → 33), amount derived from that.
  - FROM/TO address fields allow up to 500 characters.
- **ReceiptClaimDialog**
  - State and submit flow updated; wired to create-claim and attachment-upload API.
- **ClaimsManagement**
  - Uses new hooks; passes API data into stat cards, charts, and dialogs; orchestrates dialogs and detail sheet.
- **API client**
  - `claims.ts`: types, fetch/create/update/delete, attachments, stats, monthly spend, mileage rate, `calculateDistance`.
  - `constants.ts`: claim routes and `CALCULATE_DISTANCE`, `GEOCODE`, `REVERSE_GEOCODE`.

### Frontend — Attendance / Location

- **LocationManager (Work Locations)**
  - Replaced direct Nominatim (OpenStreetMap) with backend: address → coordinates via `POST /api/geocode`.
- **useGeolocation** (clock-in “Tap to get current location”)
  - Replaced direct Nominatim reverse call with backend: coordinates → location name via `POST /api/reverse-geocode`.
- **geocode.ts**
  - New client: `geocodeAddress(address)` and `reverseGeocode(lat, lng)` calling backend geocode and reverse-geocode endpoints.