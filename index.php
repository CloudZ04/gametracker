<?php
require_once 'includes/db.php';
session_start();

$bgQuery = $conn->query("SELECT portrait_image_url FROM games WHERE portrait_image_url IS NOT NULL AND portrait_image_url != '' ORDER BY RAND() LIMIT 8");
$coverUrls = [];
while ($row = $bgQuery->fetch_assoc()) {
    $coverUrls[] = $row['portrait_image_url'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GameTracker.gg - Welcome</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <style>
    body {
      margin: 0 !important;
      background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
      color: white;
      font-family: 'Rubik', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .main-content {
      padding-top: 70px;
      flex: 1;
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    }

    .left-panel {
      padding: 3rem;
      background: rgba(0, 0, 0, 0.4);
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .right-panel {
      display: flex;
      flex-direction: column;
    }

    .section-box {
      flex: 1;
      padding: 2rem;
      background: rgba(0, 0, 0, 0.3);
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      position: relative;
      overflow: hidden;
    }

    h1,
    h2 {
      font-family: 'Rubik', sans-serif;
    }

    p,
    .btn {
      font-family: 'Rubik', sans-serif;
    }

    .btn-purple {
      background-color: #b200ff;
      border: none;
      color: white;
      font-weight: 600;
    }

    .btn-purple:hover {
      background-color: #9a00cc;
    }

    .cover-bg-stack, .cover-bg-roadmap {
      position: absolute;
      top: -40px;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 0;
      opacity: 0.2;
      pointer-events: none;
    }

    .cover-bg-stack img, .cover-bg-roadmap img {
      height: 400px;
      object-fit: cover;
      margin-top: 80px;
      margin-left: -40px;
      transform: rotate(-2deg);
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 1);
    }

    .cover-bg-stack img{
      height: 350px;
    }

    .section-box .content {
      position: relative;
      z-index: 1;
    }

    .left-panel {
      position: relative;
      overflow: hidden;

    }

    .left-panel::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 18% 20%, rgba(178, 0, 255, 0.24), transparent 55%),
        radial-gradient(circle at 86% 82%, rgba(101, 55, 255, 0.2), transparent 45%);
      pointer-events: none;
      z-index: 0;
    }

    .left-panel-showcase {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: none;
      background: linear-gradient(135deg, #15151e 0%, #1a1a26 100%);
      border: 1px solid rgba(178, 0, 255, 0.18);
      border-radius: 18px;
      padding: 2rem;
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    .showcase-brand-row {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      margin-bottom: 1.2rem;
    }

    .showcase-logo {
      width: 68px;
      height: 68px;
      object-fit: contain;
      flex-shrink: 0;
    }

    .showcase-brand-text {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .showcase-label {
      font-family: 'Rubik', sans-serif;
      font-size: 0.76rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.7);
      margin: 0;
    }

    .showcase-brand-name {
      font-family: 'Rubik', sans-serif;
      font-size: clamp(1.3rem, 2vw, 1.7rem);
      color: #fff;
      margin: 0;
      line-height: 1.2;
    }

    .showcase-headline {
      font-size: clamp(2.05rem, 3.35vw, 2.95rem);
      line-height: 1.14;
      margin: 0 0 0.75rem;
      color: #fff;
      text-wrap: balance;
    }

    .showcase-headline .accent {
      color: #d086ff;
    }

    .showcase-capabilities {
      margin: 0 0 1.45rem;
      padding: 0.85rem;
      border-radius: 11px;
      background: rgba(255, 255, 255, 0.045);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .capabilities-title {
      margin: 0 0 0.5rem;
      font-family: 'Rubik', sans-serif;
      font-size: 0.8rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.72);
      font-weight: 600;
    }

    .capability-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.45rem;
    }

    .capability-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 0;
      padding: 0.46rem 0.55rem;
      border-radius: 8px;
      font-family: 'Rubik', sans-serif;
      font-size: 0.94rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.92);
      background: rgba(255, 255, 255, 0.045);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .capability-item i {
      color: #d086ff;
      font-size: 0.96rem;
    }

    nav+div {
      display: none !important;
    }


    .feature-list {
      list-style: none;
      padding: 0;
      margin-top: 2rem;
    }

    .feature-item {
      margin: 25px 0;
      position: relative;
      perspective: 800px;
      height: 60px;
      opacity: 1;
      transform: none;
    }

    .feature-card {
      position: relative;
      width: 100%;
      padding: 10px 15px;
      background: rgba(20, 10, 40, 0.5);
      border-radius: 10px;
      border-left: 4px solid #b200ff;
      box-shadow: 0 0 15px rgba(178, 0, 255, 0.3);
      display: flex;
      align-items: center;
      transform-style: preserve-3d;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .feature-card:hover {
      transform: scale(1.05);
      box-shadow: 0 0 20px rgba(178, 0, 255, 0.7);
    }

    .feature-card .feature-icon {
      transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .feature-card:hover .feature-icon {
      transform: scale(1.2);
    }

    .feature-icon {
      font-size: 22px;
      margin-right: 15px;
      display: inline-flex;
      justify-content: center;
      align-items: center;
      width: 36px;
      height: 36px;
      background: linear-gradient(45deg, #b200ff, #e100ff);
      border-radius: 50%;
      box-shadow: 0 0 12px rgba(178, 0, 255, 0.7);
    }

    .feature-icon i {
      font-size: 28px;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      height: 100%;
    }

    .feature-text {
      font-family: 'Rubik', sans-serif;
      font-size: 18px;
      font-weight: 600;
      color: #fff;
      position: relative;
    }

    /* Modern purple button system */
    .btn-enhanced,
    .btn-controller {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 52px;
      padding: 0.76rem 1.2rem;
      border-radius: 12px;
      border: 1px solid transparent;
      font-family: 'Rubik', sans-serif;
      font-weight: 700;
      font-size: 0.98rem;
      letter-spacing: 0.02em;
      line-height: 1.15;
      transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease, border-color 0.22s ease;
      text-decoration: none;
      cursor: pointer;
    }

    .btn-primary-enhanced {
      background: linear-gradient(135deg, #a900ff 0%, #d34bff 100%);
      color: #fff;
      border-color: rgba(211, 75, 255, 0.5);
      box-shadow: 0 10px 22px rgba(169, 0, 255, 0.34);
    }

    .btn-secondary-enhanced {
      background: linear-gradient(135deg, rgba(169, 0, 255, 0.28), rgba(211, 75, 255, 0.2));
      color: #fff;
      border-color: rgba(208, 134, 255, 0.42);
      box-shadow: 0 10px 22px rgba(120, 35, 180, 0.28);
    }

    .btn-enhanced:hover {
      transform: translateY(-2px);
    }

    .btn-primary-enhanced:hover {
      color: #fff;
      box-shadow: 0 14px 28px rgba(169, 0, 255, 0.46);
    }

    .btn-secondary-enhanced:hover {
      color: #fff;
      background: linear-gradient(135deg, rgba(169, 0, 255, 0.36), rgba(211, 75, 255, 0.28));
      box-shadow: 0 14px 28px rgba(120, 35, 180, 0.34);
    }

    .btn-enhanced:active,
    .btn-controller:active {
      transform: translateY(0);
    }

    .glow-button {
      margin: 1.2rem 0 0;
      width: 100%;
    }

    .btn-controller {
      width: 100%;
      max-width: 300px;
      background: linear-gradient(135deg, #9f00f5 0%, #c944ff 100%);
      color: #fff;
      border-color: rgba(211, 75, 255, 0.52);
      box-shadow: 0 12px 24px rgba(159, 0, 245, 0.34);
      font-family: 'Rubik', sans-serif;
      font-size: 0.95rem;
      text-transform: uppercase;
    }

    .btn-controller:hover {
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 16px 30px rgba(159, 0, 245, 0.44);
    }

    .controller-dots {
      display: none;
    }

    a {
      text-decoration: none !important;
    }

    a.btn-enhanced,
    a.btn-controller {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-enhanced,
    .btn-primary-enhanced {
      opacity: 1 !important;
      filter: none !important;
      pointer-events: auto !important;
      color: #fff !important;
    }

    @media (max-width: 991px) {
      .main-content {
        grid-template-columns: 1fr;
        width: 100vw !important;
      }
      .left-panel,
      .right-panel {
        width: 100vw !important;
        padding: 1.5rem 1rem;
        box-sizing: border-box;
      }
      .section-box {
        width: 100%;
        padding: 1.5rem 1rem;
        margin-bottom: 1.5rem;
        align-items: center !important;
      }
      .section-box .content {
        width: 100%;
        text-align: center;
      }
      .feature-list {
        margin-top: 1.5rem;
      }
      .feature-card {
        padding: 8px 12px;
      }
      .btn-enhanced,
      .btn-controller {
        width: 100%;
        margin: 0.5rem 0;
      }
      .cover-bg-stack {
        opacity: 0.1;
      }
      .cover-bg-stack img {
        height: 120px;
        margin-left: -10px;
      }
      .glow-button {
        width: 100%;
      }
      h1 {
        font-size: 2rem;
      }
      .feature-text {
        font-size: 0.95rem;
      }
      .mt-4.d-flex.gap-3 {
        flex-direction: column !important;
        gap: 0.5rem !important;
        align-items: stretch !important;
      }

      .left-panel-showcase {
        padding: 1.25rem 0.95rem;
        border-radius: 14px;
      }

      .showcase-brand-row {
        margin-bottom: 0.85rem;
      }

      .showcase-logo {
        width: 56px;
        height: 56px;
      }

      .showcase-capabilities {
        padding: 0.72rem 0.7rem;
      }

      .capability-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <?php include 'includes/nav.php'; ?>

  <div class="main-content">
    <div class="left-panel position-relative overflow-hidden">
      <div class="content position-relative z-1">
        <div class="left-panel-showcase">
          <div class="showcase-brand-row">
            <img src="images/logo.svg" class="showcase-logo" alt="GameTracker logo">
            <div class="showcase-brand-text">
              <p class="showcase-brand-name"><span style="color: #B200FF;">Game</span>Tracker.gg</p>
              <p class="showcase-label">Game tracking, refined</p>
            </div>
          </div>
          <h1 class="showcase-headline">Your gaming journey, <span class="accent">organized in one place</span>.</h1>

          <div class="showcase-capabilities">
            <p class="capabilities-title">What you can do with GT</p>
            <div class="capability-grid">
              <p class="capability-item"><i class="ph ph-compass"></i>Explore Upcoming Releases</p>
              <p class="capability-item"><i class="ph ph-clock-countdown"></i>Browse the Release Timeline</p>
              <p class="capability-item"><i class="ph ph-game-controller"></i>View Detailed Game Pages</p>
              <p class="capability-item"><i class="ph ph-chart-line-up"></i>Track Your Game Status</p>
              <p class="capability-item"><i class="ph ph-bookmark-simple"></i>Manage Your Wishlist</p>
              <p class="capability-item"><i class="ph ph-star"></i>Connect with the Community</p>
              <p class="capability-item"><i class="ph ph-steam-logo"></i>Sync Steam Achievements</p>
              <p class="capability-item"><i class="ph ph-star"></i>Rate Your Favourite Games</p>
            </div>
          </div>

          <?php if (!isset($_SESSION['user_id'])): ?>
          <div class="mt-4 d-flex gap-3">
            <a href="auth/login.php" class="btn-enhanced btn-primary-enhanced">
              Login
            </a>
            <a href="auth/register.php" class="btn-enhanced btn-secondary-enhanced">
              Register
            </a>
          </div>
          <?php endif; ?>

          <a href="#first-time" class="first-time-btn" id="firstTimeBtn">
            <i class="ph ph-info"></i>
            First time visiting?
            <i class="ph ph-caret-down first-time-arrow"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="right-panel">
      <div class="section-box d-flex flex-column justify-content-center align-items-start">
        <div class="cover-bg-stack">
          <?php foreach ($coverUrls as $url): ?>
          <img src="<?= htmlspecialchars($url) ?>" alt="Game Cover">
          <?php endforeach; ?>
        </div>
        <div class="content">
          <h2><i class="ph ph-game-controller"></i> Explore Upcoming Games</h2>
          <p class="mt-2">Check out games that have released so far in 2025, and games that are releasing soon and see
            what might catch your eye!</p>
          <div class="glow-button"><a href="timeline.php">
              <button class="btn-controller">
                Browse Timeline
                <div class="controller-dots">
                  <div class="controller-dot"></div>
                  <div class="controller-dot"></div>
                  <div class="controller-dot"></div>
                </div>
              </button></a>
          </div>
        </div>
      </div>
      <div class="section-box d-flex flex-column justify-content-center align-items-start">
        <div class="cover-bg-roadmap">
          <img
            src="https://img.freepik.com/free-photo/software-development-programming-coding-software_587448-4991.jpg?t=st=1746648443~exp=1746652043~hmac=253dc007c1d640523dcd5728b4e5ba93c3b842ffcc90b58625195aca3a57f567&w=1380"
            alt="Game Cover">
        </div>
        <div class="content">
          <h2><i class="ph ph-code"></i> View Our Roadmap</h2>
          <p class="mt-2">See what features are planned and what's already been completed.</p>
          <div class="glow-button"><a href="roadmap.php">
              <button class="btn-controller">
                Roadmap
                <div class="controller-dots">
                  <div class="controller-dot"></div>
                  <div class="controller-dot"></div>
                  <div class="controller-dot"></div>
                </div>
              </button></a>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      // Animate buttons in
      const buttons = document.querySelectorAll('.btn-enhanced, .glow-button');

      gsap.from(buttons, {
        opacity: 0,
        y: 0,
        stagger: 0.15,
        duration: 0.8,
        ease: "power3.out",
        onComplete: () => {
          buttons.forEach(btn => gsap.set(btn, {
            clearProps: "transform"
          }));
        }
      });


    });
  </script>

  <!-- First Time Visitor Section -->
  <section id="first-time" class="first-time-section">
    <div class="container">
      <div class="first-time-header">
        <span class="first-time-tag">Before you dive in</span>
        <h2 class="first-time-title">What to know as a <span class="accent">first-time visitor</span></h2>
        <p class="first-time-subtitle">GameTracker is an ongoing personal project — not a finished commercial product, and I have no plans to make it one as of yet.
          Here's an honest overview of where things stand.</p>
      </div>

      <div class="ft-disclaimer">
        <i class="ph-fill ph-warning"></i>
        <span><strong>How game search works:</strong> GameTracker sources its game data from <a href="https://rawg.io" target="_blank" rel="noopener">
          RAWG</a>, a community-maintained database. When you search for a game, it first checks the local database — if no match is found, it queries
          RAWG and imports the result automatically. This means some games may be missing, have incomplete metadata, or occasionally pull in the wrong
          cover art. If a game you're looking for doesn't appear, try searching by its exact title.</span>
      </div>

      <div class="first-time-grid">

        <div class="ft-card ft-expect">
          <div class="ft-card-icon"><i class="ph-fill ph-check-circle"></i></div>
          <h3>What to expect</h3>
          <ul>
            <li>Browse and search a large catalogue of games via RAWG &amp; IGDB</li>
            <li>Track your play status (Playing, Beaten, Completed, etc.)</li>
            <li>A personal profile with your collection and stats</li>
            <li>Steam achievement syncing (if you connect your account)</li>
            <li>A wishlist, game reviews, and a social follow/friend system</li>
            <li>A release timeline and patch notes for this project</li>
          </ul>
        </div>

        <div class="ft-card ft-not-expect">
          <div class="ft-card-icon"><i class="ph-fill ph-x-circle"></i></div>
          <h3>What not to expect</h3>
          <ul>
            <li>A polished, fully production-ready application</li>
            <li>100% accurate game data — APIs sometimes return wrong covers or metadata</li>
            <li>All features to work as expected — some may be missing entirely, or not working as intended.</li>
            <li>Guaranteed uptime — this runs on a personal dev server</li>
            <li>A full support team or response SLA</li>
            <li>Every edge case handled gracefully</li>
          </ul>
        </div>

        <div class="ft-card ft-broken">
          <div class="ft-card-icon"><i class="ph-fill ph-wrench"></i></div>
          <h3>Known issues</h3>
          <ul>
            <li>Some game cover images may be incorrect or missing</li>
            <li>DLC detection is imperfect — a few DLCs may appear as full games</li>
            <li>Steam sync only works for games with a linked Steam App ID</li>
            <li>Some older pages may not yet follow the latest design</li>
            <li>Character data is sparse for most games and often incorrect or incomplete.</li>
          </ul>
        </div>

        <div class="ft-card ft-unstable">
          <div class="ft-card-icon"><i class="ph-fill ph-warning"></i></div>
          <h3>May be unstable</h3>
          <ul>
            <li>Bulk image refresh and DLC scan (admin tools) can timeout on large runs</li>
            <li>Messaging and social features are early — edge cases may cause errors</li>
            <li>Game request approvals are manual and may take a while</li>
            <li>The database is periodically reset during development</li>
          </ul>
        </div>

      </div>

      <p class="first-time-footer-note">
        This project is built and maintained solo. If something breaks, feel free to report it via the <a href="support.php">Support</a> page.
      </p>
    </div>
  </section>

  <style>
    /* ── First-time button ────────────────────── */
    .first-time-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 1.5rem;
      color: #9b9bb3;
      font-size: 0.85rem;
      text-decoration: none;
      border: 1px solid rgba(178,0,255,0.2);
      border-radius: 20px;
      padding: 0.45rem 1rem;
      transition: all 0.25s ease;
      background: rgba(178,0,255,0.05);
    }
    .first-time-btn:hover {
      color: #d086ff;
      border-color: rgba(178,0,255,0.5);
      background: rgba(178,0,255,0.1);
    }
    .first-time-arrow {
      animation: bounce-down 1.6s ease-in-out infinite;
    }
    @keyframes bounce-down {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(4px); }
    }

    /* ── Section wrapper ──────────────────────── */
    .first-time-section {
      background: #13131c;
      border-top: 1px solid rgba(178,0,255,0.15);
      padding: 5rem 0 4rem;
    }
    .first-time-header {
      text-align: center;
      margin-bottom: 3rem;
    }
    .first-time-tag {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #b200ff;
      background: rgba(178,0,255,0.1);
      border: 1px solid rgba(178,0,255,0.25);
      border-radius: 20px;
      padding: 0.3rem 0.9rem;
      margin-bottom: 1rem;
    }
    .first-time-title {
      font-family: 'Rubik', sans-serif;
      font-size: clamp(1.5rem, 3vw, 2.2rem);
      font-weight: 700;
      color: #fff;
      margin-bottom: 0.75rem;
    }
    .first-time-subtitle {
      color: #7e7e9a;
      font-size: 0.95rem;
      max-width: 560px;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* ── Disclaimer banner ────────────────────── */
    .ft-disclaimer {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      background: rgba(245, 158, 11, 0.08);
      border: 1px solid rgba(245, 158, 11, 0.35);
      border-left: 4px solid #f59e0b;
      border-radius: 10px;
      padding: 0.9rem 1.1rem;
      margin-bottom: 2.5rem;
      max-width: 760px;
      margin-left: auto;
      margin-right: auto;
    }
    .ft-disclaimer i {
      color: #f59e0b;
      font-size: 1.1rem;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .ft-disclaimer span {
      font-size: 0.85rem;
      color: #c8a84b;
      line-height: 1.55;
    }
    .ft-disclaimer span strong { color: #f0c060; }
    .ft-disclaimer span a { color: #f0c060; text-underline-offset: 2px; }
    .ft-disclaimer span a:hover { color: #fff; }

    /* ── Card grid ────────────────────────────── */
    .first-time-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2.5rem;
    }
    .ft-card {
      background: #1e1e2f;
      border-radius: 14px;
      padding: 1.6rem 1.5rem;
      border: 1px solid rgba(255,255,255,0.06);
      position: relative;
      overflow: hidden;
    }
    .ft-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
    }
    .ft-expect::before   { background: linear-gradient(90deg,#22c55e,#16a34a); }
    .ft-not-expect::before { background: linear-gradient(90deg,#ef4444,#b91c1c); }
    .ft-broken::before   { background: linear-gradient(90deg,#f59e0b,#d97706); }
    .ft-unstable::before { background: linear-gradient(90deg,#b200ff,#7700bb); }

    .ft-card-icon {
      font-size: 1.6rem;
      margin-bottom: 0.75rem;
    }
    .ft-expect   .ft-card-icon { color: #22c55e; }
    .ft-not-expect .ft-card-icon { color: #ef4444; }
    .ft-broken   .ft-card-icon { color: #f59e0b; }
    .ft-unstable .ft-card-icon { color: #b200ff; }

    .ft-card h3 {
      font-size: 1rem;
      font-weight: 700;
      color: #e2e2e8;
      margin-bottom: 1rem;
      font-family: 'Rubik', sans-serif;
    }

    .ft-card ul {
      list-style: none;
      padding: 0; margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.55rem;
    }
    .ft-card ul li {
      font-size: 0.85rem;
      color: #9b9bb3;
      line-height: 1.5;
      padding-left: 1.1rem;
      position: relative;
    }
    .ft-card ul li::before {
      content: '–';
      position: absolute;
      left: 0;
      color: #444;
    }

    /* ── Footer note ──────────────────────────── */
    .first-time-footer-note {
      text-align: center;
      font-size: 1rem;
      color: #5a5a7a;
    }
    .first-time-footer-note a {
      color: #b200ff;
      text-decoration: none;
    }
    .first-time-footer-note a:hover { text-decoration: underline; }
  </style>

  <script>
    document.getElementById('firstTimeBtn').addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('first-time').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  </script>

  <?php include 'includes/footer.php'; ?>

</body>

</html>