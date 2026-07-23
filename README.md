# GameTracker

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Deployed-2496ED?style=flat-square&logo=docker&logoColor=white)
![Render](https://img.shields.io/badge/Hosted_on-Render-46E3B7?style=flat-square&logo=render&logoColor=white)
![GitHub last commit](https://img.shields.io/github/last-commit/CloudZ04/gametracker?style=flat-square)

**GameTracker** is a personal game tracking web app — think Letterboxd or Backloggd, but built from scratch as a solo project. Track what you're playing, what you've beaten, and what's collecting dust on your backlog.

**Live:** [gametracker-9grx.onrender.com](https://gametracker-9grx.onrender.com)

> **Note:** The live site is hosted on Render's free tier. The first page load may take 30–60 seconds if the server has been idle. This is expected — just wait for it to wake up and it will be fast afterwards.

---

## Features

- Browse and search a large catalogue of games via RAWG & IGDB
- Track your play status — Playing, Beaten, Completed, Shelved, Want to Play, Abandoned
- Personal profile with your collection stats and game history
- Steam achievement syncing (connect your Steam account)
- Wishlist, game reviews, and ratings
- Social follow/friend system and direct messaging
- Release timeline for upcoming and recent games
- DLC detection and separation from base games
- Admin tools for game management, DLC scanning, and image refresh
- Patch notes with version history

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (procedural) |
| Database | MySQL 8.4 (Aiven hosted) |
| Frontend | Bootstrap 5, Bootstrap Icons, Phosphor Icons |
| Game Data | RAWG API, IGDB/Twitch API |
| Achievements | Steam Web API |
| Characters | GiantBomb API |
| Markdown | Parsedown |
| Hosting | Render (Docker + Apache) |

---

## Honest Disclaimers

This is an ongoing personal project — not a finished product.

**What to expect:**
- A large game catalogue sourced from RAWG and IGDB
- Working status tracking, profiles, and social features
- Steam achievement syncing for supported games

**What not to expect:**
- 100% accurate game data — APIs occasionally return wrong covers or metadata
- All features working perfectly in every edge case
- Guaranteed uptime — this runs on free hosting with no SLA

**Known issues:**
- Some game cover images may be incorrect or missing
- DLC detection is imperfect — some DLCs may appear as full games
- Steam sync only works for games with a linked Steam App ID
- Character data is sparse and often incomplete
- New profile image uploads do not persist across Render redeploys (no persistent disk on free tier)

**May be unstable:**
- Bulk image refresh and DLC scan (admin tools) can timeout on large runs
- Messaging and social features are early — edge cases may cause errors

---

## How Game Search Works

GameTracker sources game data from [RAWG](https://rawg.io), a community-maintained database. When you search for a game, it first checks the local database — if no match is found, it queries RAWG and imports the result automatically. This means some games may be missing, have incomplete metadata, or occasionally pull in the wrong cover art. If a game you're looking for doesn't appear, try searching by its exact title.

---

## Local Development

**Requirements:** XAMPP (Apache + PHP 8.2 + MySQL), or any LAMP stack.

1. Clone the repo into your web server's root:
   ```bash
   git clone https://github.com/CloudZ04/gametracker.git
   ```

2. Import the database schema from `sql/` using phpMyAdmin or MySQL CLI.

3. Copy `.env.example` to `.env` and fill in your credentials:
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=gametracker
   RAWG_API_KEY=your_key
   IGDB_CLIENT_ID=your_key
   IGDB_CLIENT_SECRET=your_key
   STEAM_API_KEY=your_key
   GIANTBOMB_API_KEY=your_key
   ```

4. Visit `http://localhost/gametracker/`

---

## Deployment

The live site is deployed on [Render](https://render.com) using Docker, with the database hosted on [Aiven](https://aiven.io) MySQL. Every push to `main` triggers an automatic redeploy.

See [DEPLOYMENT.md](DEPLOYMENT.md) for full setup instructions.

---

*Built and maintained solo by [KyleDevs](https://github.com/CloudZ04)*
