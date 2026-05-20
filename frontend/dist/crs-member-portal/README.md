# CRS Member Portal

Standalone member-facing portal for CRS Holdings Employees Credit Cooperative.

## Files

```text
index.html          Login page
dashboard.html      Member portal dashboard
css/styles.css      Shared portal styles
js/auth.js          Login/session handling
js/api.js           Backend API connector and demo fallback
js/portal.js        Dashboard navigation and rendering
```

## Local Demo

Recommended same-origin demo URL:

```text
http://localhost:5174/crs-member-portal/index.html
```

The portal is copied into `frontend/public/crs-member-portal/` so the Vue dev server can serve it under the same origin as the admin app. This lets the demo read preview settings created in the admin system.

## API Base

Default API base:

```text
http://localhost/crs-coop/backend/api
```

For the current PHP development server, set this before loading `js/api.js` if serving the portal outside Vite:

```html
<script>
  window.CRS_API_BASE = 'http://localhost:8000'
</script>
```

When served through the Vite demo at port `5174`, the admin frontend should be started with:

```text
VITE_API_URL=http://localhost:8000
```

## Authentication Flow

1. Admin creates member access in `Settings -> Member Portal Access`.
2. Admin API stores account data in `member_portal_accounts`.
3. Member signs in through:

```text
POST /member-auth.php
```

4. Login returns a bearer token and member profile.
5. Dashboard loads member data through:

```text
GET /member-portal.php
Authorization: Bearer <token>
```

## Backend Endpoints

```text
GET/POST/PUT  /member-portal-accounts.php
POST          /member-auth.php
GET           /member-portal.php
```

## Database Tables

```text
member_portal_accounts
member_portal_sessions
member_portal_audit_logs
```

Apply:

```sql
source database/member_portal_module.sql
```

Or use the full schema:

```sql
source database/schema.sql
```

## Security Notes

- Passwords must be stored with PHP `password_hash()`.
- Member sessions store a token hash, not the raw token.
- Member-facing API responses must always be filtered by the authenticated account.
- Do not trust member IDs sent from the browser.
- Use HTTPS before enabling real member credentials.
- Add a real password-change/reset flow before production use.

## Demo Fallback

If the backend is not available, `js/api.js` can fall back to demo/local data so the UI can still be reviewed. Production should disable or remove demo fallback behavior.
