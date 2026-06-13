# MathDash

MathDash is a multilingual math learning platform built with Laravel, Inertia, and Vue.
It combines student practice, teacher tooling, analytics, formulas, games, and visual math tools in one workspace.

## What it includes

- Local and global analytics dashboards with charts and date filtering
- Math levels with XP, progress, rewards, and a student-only Hall of Fame
- Interactive games such as `1 + 1` and `Equilibrium`
- 2D and 3D geometry calculators and previews
- Integral calculator with Wolfram verification and AI-generated step support
- Formula library with multilingual content and animated explanations
- Teacher Exercise Studio with:
  - AI exercise generation
  - rich Tiptap-based editing
  - image upload and image resizing
  - freehand drawing blocks
  - math input via MathLive
  - PDF export
- Role-based access for `student`, `lecturer`, and `admin`
- UI translations for `en`, `hy`, `fr`, and `ru`

## Stack

- Backend: Laravel 12, PHP 8.2
- Frontend: Vue 3, Inertia.js, TypeScript, Vite
- Styling: Tailwind CSS 4
- Charts and visuals: ECharts, Unovis, Three.js, TresJS, Konva
- Editor: Tiptap, MathLive
- PDF export: DomPDF
- AI integrations:
  - Gemini for teacher exercises, formula steps, math levels, and AI-assisted solving
  - Wolfram Alpha for integral verification

## Product areas

### Student experience

- Practice through math levels, games, calculators, and formulas
- Earn XP and move through progress-based content
- View rewards and leaderboard-style ranking

### Lecturer experience

- Access the Teacher Exercise Studio
- Generate, edit, save, publish, and export classroom-ready content
- Upload images, insert formulas, add drawings, and build printable worksheets

### Admin experience

- Full lecturer capabilities
- Intended for platform management, content oversight, and privileged workflows

## Roles

MathDash uses three roles:

- `student`: learner-facing experience only
- `lecturer`: can use the Teacher Exercise Studio
- `admin`: full access

Route protection is enforced both in web routes and API routes.

## Demo data

The seeders create a default admin account and realistic dashboard/demo users.

Default admin login:

- Email: `admin@example.com`
- Password: `password`

Dashboard demo users are also seeded with the password `password`.

## Local setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Create environment file

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

### 3. Configure database

The app works well with SQLite for local development.

```bash
php artisan migrate --seed
```

### 4. Create storage symlink

```bash
php artisan storage:link
```

### 5. Start the app

Recommended all-in-one dev command:

```bash
composer run dev
```

That starts:

- Laravel server
- queue listener
- Laravel Pail logs
- Vite dev server

If you want the frontend and backend separately:

```bash
php artisan serve
npm run dev
```

## Environment variables

The app can run without all AI features enabled, but these services unlock the full experience.

### Required for AI features

```env
GEMINI_API_KEY=
GEMINI_MODEL=gemini-flash-latest
GEMINI_FALLBACK_MODEL=gemini-2.5-flash-lite
```

### Required for integral verification

```env
WOLFRAM_APP_ID=
```

### Useful local values

```env
APP_URL=http://127.0.0.1:8000
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

## Tests

Run the full Laravel test suite:

```bash
composer test
```

There are feature tests for important teacher-editor behavior, including:

- role restrictions
- image upload validation
- draft saving
- PDF export
- document ownership checks

## Notable implementation details

- Sidebar, dashboards, calculators, formulas, and levels are all rendered through Inertia/Vue pages
- The editor supports rich content blocks, custom image node views, freehand drawings, inline math, and PDF-friendly HTML normalization
- Formula content and explanations are locale-aware
- Hall of Fame and analytics logic already filter student-facing data appropriately
- Vite is configured with manual chunking for heavier frontend modules like charts, 3D, editor tooling, and MathLive

## Main routes

Authenticated app areas include:

- `/dashboard/local`
- `/dashboard/global`
- `/math-levels`
- `/math-levels/rewards`
- `/mathematics/twoD`
- `/mathematics/threeD`
- `/mathematics/integral`
- `/game/oneplusone`
- `/game/equilibrium`
- `/fomula`
- `/teacher/exercises/studio`

## Project status

This is no longer a starter Laravel app. It is a feature-rich education product with:

- custom student workflows
- teacher authoring tools
- multilingual content
- analytics
- AI-assisted generation
- printable lesson output

## License

This project currently inherits the repository's existing MIT license setup unless you decide to relicense it for commercial distribution.
