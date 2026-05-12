# CRS Holdings Corp — Employees Credit Cooperative
## Loan Management System v1.0

### Stack
- **Database**: MySQL 8+
- **Backend**: PHP 8.1+ (Apache/XAMPP)
- **Frontend**: Vue 3 + Vite + Pinia + Vue Router

---

## Setup Instructions

### 1. Database

```sql
-- Run in phpMyAdmin or MySQL CLI:
source database/schema.sql
```

Or copy-paste `database/schema.sql` into phpMyAdmin.

---

### 2. Backend (PHP)

1. Copy the `backend/` folder to your web server:
   ```
   htdocs/crs-coop/backend/   (XAMPP)
   www/crs-coop/backend/      (Laragon)
   ```

2. Edit `backend/config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'crs_coop');
   define('DB_USER', 'root');
   define('DB_PASS', '');   // your MySQL password
   ```

3. Test the API in your browser:
   ```
   http://localhost/crs-coop/backend/api/members.php
   http://localhost/crs-coop/backend/api/loan-types.php
   ```

---

### 3. Frontend (Vue)

```bash
cd frontend
npm install
cp .env.example .env.local
# Edit .env.local → set VITE_API_URL to your PHP backend URL
npm run dev
```

Open: `http://localhost:5173`

For production build:
```bash
npm run build
# Copy dist/ to htdocs/crs-coop/frontend/
```

---

## Project Structure

```
crs-coop/
├── database/
│   └── schema.sql              ← MySQL schema + seed data
├── backend/
│   ├── config/
│   │   └── database.php        ← DB connection config
│   ├── helpers/
│   │   └── helpers.php         ← CORS, JSON responses, loan calculator
│   └── api/
│       ├── members.php         ← Members CRUD API
│       ├── loans.php           ← Loans API + amortization generator
│       └── loan-types.php      ← Loan type lookup
└── frontend/
    ├── index.html
    ├── vite.config.js
    ├── package.json
    └── src/
        ├── main.js
        ├── router.js
        ├── style.css
        ├── App.vue             ← Root layout with sidebar
        ├── composables/
        │   ├── useApi.js       ← All API calls
        │   ├── useLoanCalc.js  ← Diminishing balance calculator
        │   └── useToast.js     ← Toast notifications
        ├── views/
        │   ├── DashboardView.vue
        │   ├── MembersView.vue       ← Members + 201 file
        │   ├── LoanOfficerView.vue   ← 3-pane officer desk (Direction D)
        │   ├── PipelineView.vue      ← Kanban pipeline
        │   └── MonitoringView.vue    ← Amortization monitoring
        └── components/
            └── shared/
                └── InfoRow.vue
```

---

## Features

### Members & 201 File
- Search, filter, add, edit members
- View complete 201 file (basic info + loans)
- Jump directly to "New Loan" for a member

### Loan Officer Desk (Direction D — 3-Pane)
- **Left**: Member list with live search
- **Middle**: Loan application form with live sliders
  - Auto-filled from member database
  - Loan type selector (Commodity, Salary, Emergency, Educational, Multi)
  - Amount/term range sliders
  - Frequency selector (Monthly, Bi-Monthly, Weekly)
  - Co-maker selection from member DB
  - Live amortization summary (periods, first/last/total payment)
- **Right**: Live PDF preview (5 pages)
  - Page 1: Loan Application Form
  - Page 2: Authority to Deduct + full schedule
  - Pages 3–5: Full amortization table
  - UNSIGNED stamp
  - Attach signed copy uploader

### Loan Pipeline
- Kanban board: Draft → Pending → Approved → Active → Closed / Rejected
- Quick approve/reject buttons
- Status change modal

### Monitoring
- All active loans with amortization schedule
- Period-by-period tracking (Pending / Paid / Overdue / Partial)
- Summary stats per loan

---

## Loan Math (Diminishing Balance)

Matches CRS sample: ₱60,000 / 36 months / Bi-Monthly
- Period 1:  ₱833.33 principal + ₱600.00 interest = **₱1,433.33** ✓
- Period 72: ₱833.33 principal + ₱16.67 interest  = **₱850.00** ✓

Formula:
- `principalPerPeriod = amount / nPeriods`
- `interest = remaining × (annualRate/12) × periodFactor`
- `periodFactor` = 1 (monthly), 0.5 (bimonthly), 0.25 (weekly)

---

## Next Steps (Phase 2)

- [ ] Print-to-PDF using browser `window.print()` or wkhtmltopdf
- [ ] Payment recording (mark periods as Paid + OR number)
- [ ] User authentication (login for loan officers)
- [ ] Reports: Collection summary, outstanding balance report
- [ ] SMS/email notifications for due dates
- [ ] Overdue flagging automation
