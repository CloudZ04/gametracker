# Deployment Guide (GitHub + Vercel)

This project is prepared for a community PHP runtime on Vercel.

## 1) Initialize Git Repository

Run these commands in the project root:

```bash
git init
git add .
git commit -m "Prepare project for Vercel deployment"
git branch -M main
git remote add origin https://github.com/<your-user>/<your-repo>.git
git push -u origin main
```

## 2) Connect GitHub Repo to Vercel

1. Open [Vercel Dashboard](https://vercel.com/dashboard).
2. Click **Add New... -> Project**.
3. Import your GitHub repository.
4. Keep project root as repository root.
5. Deploy.

Vercel will auto-deploy on every push to `main` afterward.

## 3) Configure Environment Variables (Vercel)

Set these variables in Vercel Project Settings -> Environment Variables:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `DB_PORT` (usually `3306`)

Use the same names as `.env.example`.

## 4) What Was Added For Deployment

- `.gitignore` for logs, local env files, and editor/OS noise.
- `.env.example` with DB variable names.
- `vercel.json` using `vercel-php` community runtime for PHP files and static serving.
- `includes/db.php` now reads DB settings from environment variables with local fallbacks.

## 5) Post-Deploy Verification Checklist

After first deployment, verify:

1. Homepage loads (`/` -> `index.php`).
2. Login and register routes respond:
   - `/auth/login.php`
   - `/auth/register.php`
3. API endpoint responds (example):
   - `/api/search-games.php?q=zelda`
4. DB connectivity works (no `Connection failed` output).
5. Static assets load (images/CSS/JS).

## 6) Known Risk / Fallback

This app uses a community PHP runtime on Vercel. If you hit runtime limitations (extensions, session behavior, timeouts), use this fallback:

- Keep frontend/static delivery on Vercel.
- Move PHP backend to a PHP-native host (shared hosting, Render, Railway, Fly.io, etc.).
