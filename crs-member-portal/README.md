# CRS Member Portal

Standalone member-facing dashboard prototype for the CRS Holdings Employees Credit Cooperative system.

## Files

```text
index.html          Login page
dashboard.html      Member portal
css/styles.css      Shared styles
js/auth.js          Login/session handling
js/api.js           Backend API connector
js/portal.js        Dashboard navigation and rendering
```

## Current Behavior

The portal first tries to call the CRS backend API. If the backend is not ready, it falls back to demo data so the UI can be reviewed.

Default API base:

```text
http://localhost/crs-coop/backend/api
```

Override it before loading the scripts if needed:

```html
<script>
  window.CRS_API_BASE = 'https://your-domain.com/api'
</script>
```

## Backend Endpoints To Add

The developer team should add member-facing endpoints similar to:

```text
POST /member-auth.php
GET  /member-portal.php
```

Recommended production response for `GET /member-portal.php`:

```json
{
  "success": true,
  "data": {
    "member": {},
    "loans": [],
    "payments": [],
    "shareCapital": [],
    "beneficiaries": []
  }
}
```

## Production Requirements

- Add real member authentication.
- Use secure password hashing.
- Use server-side sessions or JWT tokens.
- Every member endpoint must filter by the logged-in member ID.
- Do not trust member IDs sent from the browser.
- Add a real `beneficiaries` database table if this module will be persisted.
- Attachments should be stored as files/object storage, with metadata saved in MySQL.

## Integration Notes

This portal is separate from the admin/officer system. It should share the same database, but use restricted member-only API routes.
