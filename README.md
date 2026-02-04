# CSSKiller Commander UI

A full-stack admin UI to remotely run CSSKiller/Tonics commands from the browser. It ships as a PHP plugin (secured HTTP API) plus a modern React/TypeScript frontend (Vite + Tailwind + shadcn/ui + MobX).

- Secure API endpoints under `/commander-ui` with Bearer token auth
- One-click execution of common maintenance commands
- Flexible Options Builder with reusable Presets
- Multi-site management (persisted), per-site tokens, active-site memory
- Responsive UI, animated progress, auto-scroll to results

---

## Contents

- [Architecture](#architecture)
- [Backend (PHP plugin)](#backend-php-plugin)
  - [Routes and endpoints](#routes-and-endpoints)
  - [Authorization](#authorization)
  - [Response envelope](#response-envelope)
  - [Examples (cURL)](#examples-curl)
- [Frontend (Vite app)](#frontend-vite-app)
  - [Features](#features)
  - [Local development](#local-development)
  - [Build](#build)
  - [Deploy options](#deploy-options)
- [Presets](#presets)
- [Usage workflow](#usage-workflow)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Project layout](#project-layout)

---

## Architecture

```
root/
├─ src/                         # PHP plugin (routes, controllers, middleware)
│  ├─ Routes.php                # /commander-ui endpoints
│  ├─ controllers/CommanderController.php
│  ├─ middlewares/AuthMiddleware.php
│  └─ templates/                # (optional) template stubs
├─ vendor/                      # composer deps
├─ vite/csskiller-plugin-commander-ui/   # React + TypeScript UI
│  ├─ csskiller-plugin-commander-ui/ # React + TypeScript UI
│  └─ assets/                     # built static assets
├─ composer.json
└─ README.md
```

- Backend exposes command endpoints and executes Core console commands in a safe, non-CLI mode.
- Frontend consumes those endpoints via Bearer token, provides a simple UI for flags/options, and persists sites/presets locally.

---

## Backend (PHP plugin)

### Routes and endpoints
Defined in `src/Routes.php` and handled in `src/controllers/CommanderController.php`.

Base path: `/commander-ui`

- `GET  /commander-ui/sites`
  - Returns a list of supported site base URLs (driven by env `COMMANDER_UI_SITES` or a comma/JSON list).
- `POST /commander-ui/init`
  - Runs Init CMS (`Core\commands\InitCMSCommand`).
- `GET  /commander-ui/versions`
  - Lists CMS versions (`Core\commands\ListCMSVersionsCommand`).
- `POST /commander-ui/cache/siteground/purge`
  - Purges SiteGround cache (`Core\commands\SiteGroundCachePurge`).
- `POST /commander-ui/env`
  - Runs Env Manager (`Core\commands\EnvManagerCommand`).
- `POST /commander-ui/migrations/all`
  - Runs migrations (`Core\MigrateAll`).

Each POST endpoint accepts a JSON body of:
```json
{
  "options": { "--some:option": "value" },
  "flags": ["--flag-a", "--flag-b"]
}
```

### Authorization
Secured by `AuthMiddleware`:
- Prefer `Authorization: Bearer <COMMANDER_UI_SECRET>` header.
- Fallback: `?token=<COMMANDER_UI_SECRET>` query parameter.
- Env key to set: `COMMANDER_UI_SECRET` (string, non-empty). Requests without a valid token return 401.

### Response envelope
All endpoints respond with the same envelope:
```json
{
  "status": 200,
  "message": "<human-friendly message>",
  "data": {
    "command": "<CommandName>",
    "class_used": "<Fully\\Qualified\\Class>",
    "options": { "--example": "value" },
    "result": <string | array | object>
  },
  "more": null
}
```
Notes:
- `result` may be a string, an array (of primitives or objects), or any JSON-serializable payload.
- The UI shows `message` inline and uses a renderer that adapts to each shape.

### Examples (cURL)
Replace `BASE_URL` and `SECRET`.

List versions:
```bash
curl -H "Authorization: Bearer SECRET" \
  "BASE_URL/commander-ui/versions"
```

Init CMS (auto-generate SANITY token):
```bash
curl -X POST -H "Authorization: Bearer SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "options": { "--set-auto-gen": "SANITY_BEARER_TOKEN" },
    "flags": []
  }' \
  "BASE_URL/commander-ui/init"
```

SiteGround purge:
```bash
curl -X POST -H "Authorization: Bearer SECRET" \
  -H "Content-Type: application/json" \
  -d '{ "options": { "--sg:purge": "example.com" } }' \
  "BASE_URL/commander-ui/cache/siteground/purge"
```

Migrate all:
```bash
curl -X POST -H "Authorization: Bearer SECRET" \
  -H "Content-Type: application/json" \
  -d '{ "options": { "--migrate:all": "" } }' \
  "BASE_URL/commander-ui/migrations/all"
```

---

## Frontend (Vite app)
Location: `vite/csskiller-plugin-commander-ui`

### Features
- Presets: quick-apply reusable options/flags; save/delete your own
- Options Builder: add/edit `--option` values and `--flags` inline
- Site manager: add/remove sites, per-site tokens, import supported sites, remember last active site
- SiteGround domain helper: pick domain from sites to set `--sg:purge`
- Animated progress: indeterminate bar while running, auto-scrolls into view on RUN
- Responsive layout: header, tabs, and pages adapt for mobile/desktop

### Local development

```cmd
cd vite/csskiller-plugin-commander-ui
pnpm install
pnpm dev
```

Open the dev URL, configure a Site + Token in the header, then run commands.

### Build
```cmd
cd vite/csskiller-plugin-commander-ui
pnpm build
```
Outputs static assets to `vite/assets/`.

### Deploy options
- Serve `vite/assets/` via your web server (Nginx/Apache) and point it at the API origin(s).
- Or embed within an existing admin area—UI calls your `/commander-ui/*` endpoints.
- If hosting UI on a different origin, configure CORS on the backend.

---

## Presets
Built-in presets (you can add your own from the UI):

- Init CMS:
  - Auto-generate SANITY_BEARER_TOKEN → `--set-auto-gen: SANITY_BEARER_TOKEN`
  - Full Init Template:
    - `--path`: `/path/to/new-cms`
    - `--set:DB_HOST`: `localhost`
    - `--set:BASE_URL`: `https://xxxxxx`
    - `--set:HOSTNAME`: `xxxxxxxxx`
    - `--set:TOKEN`: `xxxxxxxxxxxxxx`
    - `--set:DB_DATABASE`: `xxxxxxxx`
    - `--set:DB_USERNAME`: `xxxxxxxxxxx`
    - `--set:DB_PASSWORD`: `xxxxxxxxxxxxxxx`
    - `--set-auto-gen`: `SANITY_BEARER_TOKEN`

- Env Manager:
  - Auto-generate SANITY_BEARER_TOKEN → `--set-auto-gen: SANITY_BEARER_TOKEN` (optionally add `--path` to target a specific .env)
  - Update .env Template:
    - `--path`: `/path/to/.env`
    - `--set:DB_HOST`: `localhost`, plus related keys as above

- SiteGround Cache:
  - Purge Cache (Domain): `--sg:purge: <yourdomain.com>` (or pick from Sites Manager)

- Versions:
  - List CMS Versions: `--list:cms-versions`

- Migrations:
  - Migrate All: `--migrate:all`

When you click Apply, the UI switches to the **Options** tab so you can tweak values before running.

---

## Usage workflow
1. Open the UI; add a site (base URL) and set a token (same as `COMMANDER_UI_SECRET`).
2. Pick a command from the sidebar.
3. Click **Show Options** → choose a Preset or compose your own.
4. Click **Run**.
   - The screen auto-scrolls to the progress area.
   - The progress bar animates while running, then results render below.
5. Save useful options combos as Presets for reuse.

---

## Configuration
- Backend (env):
  - `COMMANDER_UI_SECRET`: required; shared secret for Bearer auth.
  - `COMMANDER_UI_SITES`: optional; JSON or comma-separated list of URLs used by `/commander-ui/sites`.
- Frontend:
  - No build-time config needed. Sites/tokens/presets persist in browser `localStorage`.

Security:
- Use HTTPS and a strong `COMMANDER_UI_SECRET`.
- Do not expose `/commander-ui/*` endpoints without auth.

---

## Troubleshooting
- 401 Unauthorized: ensure the Authorization header uses the correct secret and origin.
- Progress bar doesn’t move: hard-refresh to pick up CSS. Ensure you’re on a command screen and click RUN—an indeterminate bar should animate.
- Auto-scroll not visible: long options may push content; we auto-scroll on RUN and when loading begins. If your sticky header height differs, adjust the `headerOffset` (default ~96px) in `CommandWrapper.tsx`.
- CORS errors: if the UI is on a different origin, set up CORS on the backend.
- Site import fails: the root URL must expose `/commander-ui/sites` and accept your token.

---

## Project layout
- `composer.json`, `vendor/` — PHP deps
- `src/` — plugin source
  - `Routes.php` — endpoint registration
  - `controllers/CommanderController.php` — runs Core commands, returns envelope
  - `middlewares/AuthMiddleware.php` — Bearer auth
- `vite/csskiller-plugin-commander-ui/` — React app
  - `src/components/` — UI (commands, layout, shadcn/ui)
  - `src/stores/` — MobX stores (Commander, Site)
  - `src/lib/api.ts` — Axios wrapper (envelope-aware)
  - `vite/assets/` — built assets

---

## License
Commercial license. Do not share or redistribute without permission.
