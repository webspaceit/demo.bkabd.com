# OOP Exam System — Build Conventions

Plain PHP 8.1+ OOP + fetch(Ajax) + Tailwind CSS clone of the BKABD Laravel/Vue exam system.
No Composer/Node/SSH on hosting: `vendor/` is committed, CSS is prebuilt, deploy = FTP upload.

## Architecture

```
public/index.php            front controller (serves real files itself under cli-server)
bootstrap/app.php           autoload (.env, composer), error handling
routes.php                  loads routes/*.php (sorted) — ONE FILE PER MODULE, no merge conflicts
app/Core/                   framework: Router, Request, Response, Database, Query, Model,
                            Validator, Auth, Session, Cache, View, SmsService, HttpException
app/Controllers/            HTTP controllers (base Controller mirrors Laravel BaseController)
app/Models/                 thin models (table + fillable + casts)
app/Services/               business logic (roll generation, reports, pdf, excel…)
resources/views/            PHP templates (view path dot → folder slash)
resources/css/app.css       Tailwind input (build: npx tailwindcss@3.4.17 -c tailwind.config.js -i resources/css/app.css -o public/assets/css/app.css --minify)
public/assets/js/app.js     BK helper library — read it before writing page JS
database/                   schema.sql, seed_data.sql, demo_seed.sql, import.bat
storage/                    cache/, logs/, uploads/
```

## Routing

Register in **your module's own file** `routes/<module>.php`:

```php
use App\Core\Router;
Router::get('/api/students', [StudentController::class, 'index'], 'auth|permission:student-list');
Router::post('/api/students', [StudentController::class, 'store'], 'auth|permission:student-registration');
Router::get('/students', fn() => View::render('students.index', [...], 'layouts.dashboard'), 'auth|permission:student-list');
```

- Middleware tokens (pipe-separated): `auth`, `guest`, `permission:x|y`. CSRF is automatic for
  POST/PUT/PATCH/DELETE (header `X-CSRF-TOKEN` or body `_token`); use `no-csrf` only if unavoidable.
- Handler return: **array → JSON 200**, **string → HTML 200**, **null → you already emitted**
  (downloads/redirects). For other status codes call `Response::json($data, $code)` (exits).
- Path params: `/api/foo/{id}` → `$request->params['id']`.

## Response shapes (MUST match original — frontend contracts)

- Success: `return $this->sendResponse($data, 'message');` → `{success:true, data:..., message:...}`
- Error: `$this->sendError('Validation Error.', $errors, 404)` → `{success:false, message:..., data:...}` (+status)
- Validation failure: throw via `Validator::validate()` → 422 `{message, errors:{field:[...]}}` for Ajax,
  redirect-back-with-errors for plain form posts (auto-handled by App).
- Login failure: `Response::json(['status'=>'errors'], 401|403)` (exact original quirk).
- Lists: keep the original JSON key names (`studentList`, `roleList`, `userList`, `noticeList`,
  `divisionList`, `districtById`, `upazilaById`, `centerList`, `fullmarks`, `sessionList`,
  `recentSession`, `status:'success'|'errors'`, mark entry `{students:[...]}` …).
- Pagination: `$query->paginate(20)` returns the **full Laravel shape** `{current_page, data,
  first_page_url, from, last_page, last_page_url, links, next_page_url, path, per_page,
  prev_page_url, to, total}`. `data` rows are raw assoc arrays (like `->get()` JSON).
- Unauthenticated API → 401 `{message:'Unauthenticated.'}`; forbidden → 403 `{message:'This action is unauthorized.'}` (auto from middleware).

## Reports and documents

- Report sheets (`ReportController`) are returned as **printable HTML** (string handler → 200);
  the page has a "Print / Save as PDF" button. This matches the certificate pages and avoids
  dompdf's memory blow-up on thousand-row sheets on shared hosting.
- The three Excel exports (`*detailsexport`, `TotalInstituteInformation/0`) use
  `ReportPdf::xlsx()` (PhpSpreadsheet) and emit a real `.xlsx` download.
- `ReportPdf::noData()` emits the original self-closing 404 "No Data Found" page.
- A report button opens its endpoint in a new tab; center/institute selects live in
  `resources/views/reports/index.php` and use `/api/center` + `/api/instituteListByCenter`.
- Build a sheet through `renderSheet()` (generic `resources/views/reports/pdf/table.php`),
  passing `reportTitle`, `scopeName`, `orientation`, `summaries`, `headings` and `rows`.

## Validation

```php
$data = Validator::validate($request, [
    'name'  => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'class' => 'required|in:1,2,3,4,5,6',
    'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
], ['email.unique' => 'Custom message']);
```
Rules: `required nullable email string numeric integer boolean min:N max:N same:field
unique:table,col[,ignoreId] exists:table,col date_format:Y-m-d image mimes:a,b in:a,b,c confirmed regex:/…/`
Validated data is the returned array — use it, never `$request->all()` for mass assignment.

## Database / models

```php
Student::find($id);                              // casted row or null
Student::query()->where('session', 2025)->orderBy('id','desc')->paginate(20);
(new Query('students'))->join('institutes','institutes.id','=','students.institute_id')...
```
- **Always use bindings** (builder does it; raw SQL → `Database::select($sql, [$params])`).
- Builder backticks columns, but `select()` args are raw: reserved words need backticks
  (`->select('id','`order`')`).
- Avoid N+1: JOIN eager fields in one query (`students` list joins institutes + centers + users).
- New index needed? Add to `database/schema.sql` **and** note it in README “upgrade.sql”.

## Views (Tailwind)

- Layouts: `layouts.dashboard` (auth shell — Rubick-style layout: a deep-green `#064e3b`
  backdrop, a rounded-left `#f1f5f9` canvas, and the `.active-nav-notch` curved active nav item
  defined at the end of `resources/css/app.css`; the sidebar auto-filters by permission and the
  "Search menu…" box filters menu items client-side) and `layouts.guest`.
- Dashboard home (`resources/views/home.php`): all card values come from `GET /api/dashboard` via
  `data-stat="<key>"`; the two participation rings are computed from real attendance counts while
  the line chart and coverage map are decorative (labelled as illustrative).
- Font: `Inter` for Latin, `Noto Sans Bengali` fallback (both loaded from Google Fonts in the
  dashboard layout). Adding new arbitrary utilities (e.g. `bg-[#064e3b]`, `rounded-l-[30px]`)
  requires the Tailwind rebuild above; the built `public/assets/css/app.css` is committed. The
  notch background colours are hard-coded in `app.css` and must match the canvas (`#f1f5f9`).
- Helpers in templates: `e()`, `js()`, `csrf_field()`, `csrf_token()`, `old()`, `errors_for($f)`,
  `auth_user()`, `can($perm)`, `is_admin()`, `partial($view,$data)`, `ClassName()`, `GradeName()`,
  `MeritName()`, `GenderName()`, `EducationType()`, `DateFormat($d,$f)`, `active_menu(...)`.
- Semantic classes available (defined in resources/css/app.css): `.card .card-header .card-body
  .btn .btn-primary .btn-dark .btn-light .btn-danger .btn-sm .label .table .badge .badge-green
  .badge-red .badge-gray .field-error .filter-bar`.
- **Row actions**: every table action cell must wrap its buttons in `.row-actions`
  (`<div class="row-actions flex items-center gap-1">…</div>`, or `class="row-actions …"` on the
  `<td>`). The CSS hides the buttons behind a "⋮" kebab and reveals them on row hover —
  fine-pointer devices only; touch devices always show the buttons, and tapping the kebab pins
  them (`app.js` adds/removes `.pinned`). Leave `.row-actions` unset on rows that have no
  actions: the kebab hides via `:empty`. Don't change the visibility/opacity behaviour of
  `.row-actions > *` without keeping the `@media (hover: hover)` guard.
- The global form rule styles bare `input, select, textarea` (specificity 0,0,1) — never raise
  that specificity (no `:not(...)` chains): Tailwind v3 emits flat CSS with no cascade layers,
  so a base rule with higher specificity silently beats utilities like `bg-white/10`,
  `rounded-full` or `text-xs`. See the sidebar `#nav-search` bug for the failure mode.
- Form fields must carry `data-field="name"` and a sibling
  `<p class="field-error hidden" data-error-for="name"></p>` so `BK.showErrors` works.
- Page-level scripts: either inline `<script>` at the end of the view, or pass
  `'pageScripts' => ['/assets/js/students.js']` to the layout.

## Page JS (BK library — public/assets/js/app.js)

```js
const res = await BK.post('/api/students', payload);       // JSON, CSRF + XHR headers
BK.toast('Saved', 'success');
BK.toast('2 institutes still registered.', 'error', { label: 'View institutes', href: '/institutes?center_id=5' });
await BK.submit(formEl, async () => { await BK.post(...); }); // shows 422 errors inline
await BK.loadList('/api/students?'+qs, row => `<tr>...</tr>`, listEl, pagerEl);
BK.qs('/api/students', {page: 2, search: 'x'})             // build query strings
```
401 → auto message + redirect to /login handled inside `BK.submit`.
- `BK.toast(message, type)` accepts an optional third arg `{label, href}` for a
  follow-up action link (e.g. the `/centers` delete guard linking to the filtered
  institute list). Link toasts stay on screen longer so they can be clicked.
- Server-side flash messages are surfaced as toasts: render a pending flash as
  `<div data-flash="success" hidden><?= e($msg) ?></div>` (the dashboard layout does this
  for `flash('success')`); on load `app.js` turns any `[data-flash]` node into a `BK.toast`
  and removes it. Prefer this over a static coloured banner so feedback is consistent.

## Permissions (from DB, exact names)

`role-*`, `user-*`, `student-*`, `center-*`, `institute-*`, `division-*`, `district-*`,
`upazila-*`, `password-change*`, `report-list`, `admit-card`, `center-status`, `institute-status`,
`notice-*`, `settings`, `sms`, `mark-entry`, `count-report`, `center-detail-report`,
`institute-detail-report`, `manage-area`, `education-type-report`, `*-membership-report`,
`classwise-attend-report[2]`, `classwise-attend-report-photo[2]`, `total-institute-information`,
`center-detail-info-report`, `center-student-admitCard`, `center-detail-without-image-report`,
`documents-distribution-report`, `seatplan`, `Center-Wise-Tabulation-Sheet`,
`Center-Wise-Grade-Sheet-Count-Report`, `Center-Wise-Highest-Marks-Report`,
`Institute-Wise-Highest-Marks-Report`, `student-certificate`, `institute-certificate`,
`Center-Wise-Grade-Merit-List-Position-Count-Report`, `routine-*`, `role-place`,
`mark-distribution*`, `setting-edit`, `session-*`, `institute-validity`,
`Center-Wise-Grade-Sheet-Result-Details`, `Institute-Wise-Grade-Sheet-Result-Details`,
`Merit-List-Result-with-Position`, `grade-assign`

## Route paths (pages, from original sidebar)

`/` `/student-registration` `/students` `/institute-registration` `/institutes` `/centers`
`/divisions` `/districts` `/upazilas` `/sms` `/markentry` `/gradeassign` `/admitcards`
`/seatplan` `/student-certificate` `/institute-certificate` `/reports` `/notices` `/user`
`/routines` `/sessions` `/userrole` `/settings` `/mark-distributions` `/role-place`
`/instructions` `/passchange` `/login` `/scholarship-result` `/result/show`
`/membership-institute-registration`

## Hard rules (from blueprint Part 1.8 — do NOT copy original bugs)

1. No `dd()`, no dead/commented-out code, no debug leftovers.
2. SMS key only via `Config::get('SMS_API_KEY')` — never in code.
3. Destructive actions: DELETE/POST (original GET `*Delete/*` endpoints → convert to DELETE).
4. CSRF on every state-changing request (framework enforces — don't bypass).
5. Prepared statements everywhere.
6. Original bugs to fix while porting: `unionById` queried wrong table; `DistrictController::show`
   referenced missing class; missing `center_certificate` method; `uploadFile` had `dd()`;
   unauthenticated `changestatus`/`ins-store`/`uploadFile` → protect (except public
   institute self-registration which stays public).
7. Error/edge handling: throw `new HttpException(404, 'Not found')` or return sendError — never fatal.

## Testing

```bat
REM dev server (running already on :8090)
E:\wamp64\bin\php\php8.2.29\php.exe -S 127.0.0.1:8090 -t public public/index.php
REM endpoint checks (authenticated flows via login)
E:\wamp64\bin\php\php8.2.29\php.exe tools\smoke_test.php
E:\wamp64\bin\php\php8.2.29\php.exe tools\test_contract.php   REM public form + new URL-contract endpoints
E:\wamp64\bin\php\php8.2.29\php.exe tools\check_coverage.php  REM original /api/* path coverage vs E:\wamp64\www\exam\routes
curl -s -H "X-Requested-With: XMLHttpRequest" http://127.0.0.1:8090/api/endpoint
REM rebuild CSS after adding classes (rare):
npx tailwindcss@3.4.17 -c tailwind.config.js -i resources/css/app.css -o public/assets/css/app.css --minify
```

Sample-data login: `mehedi@gmail.com` (full access) — set/rotate its password with
`php tools/set_sample_login.php`; it records the value in `.env` as `TEST_ADMIN_PASSWORD`.
Fresh installs ship **no** default password — set one with `php tools/set_password.php <email>`.
DB: `oop_exam` on localhost, root, empty password (holds the original `testdform` data).

## Source of truth

Original Laravel app: `E:\wamp64\www\exam` — port controllers method-by-method, keeping URL +
JSON contracts. Read the original controller/route/view before porting each feature.
