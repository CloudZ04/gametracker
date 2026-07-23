<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// TODO: add your admin auth guard here (don't redirect from this page)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sync Game Characters</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg,#0f0c29,#302b63,#24243e);
           font-family:'Orbitron',sans-serif; color:#ccc; min-height:100vh; }
    .container { padding: 2rem 1rem 4rem; max-width: 1100px; }
    .panel { background: rgba(30,30,47,.9); padding: 1.25rem; border-radius: 12px; box-shadow: 0 0 18px #b200ff44; }
    .grid { display:grid; gap:1rem; grid-template-columns: 1fr auto; align-items:center; }
    .grid label { justify-self:start; }
    .card { background:#1b1e33; border:1px solid #2d3160; box-shadow: 0 0 12px rgba(178,0,255,.18); }
    .thumb { width:100%; height:160px; object-fit:cover; background:#0b0d1d; }
    .name { font-size: 1rem; font-weight: 700; color:#f2f4ff; }
    .dim { color:#9aa; font-size:.9rem; }
    .badge-mini { font-size:.75rem; }
    .sticky-actions { position: sticky; bottom: 0; background: #12142b; padding: .75rem; border-top: 1px solid #2d3160; }
    .hidden { display:none; }
  </style>
</head>
<body>
  <div class="container">
    <div class="panel mb-3">
      <h2 class="mb-3">🔄 Sync Game Characters</h2>
      <div class="grid mb-3">
        <label class="form-label m-0" for="gameId">Game ID</label>
        <input class="form-control" id="gameId" placeholder="e.g., 123">
      </div>
      <div class="grid mb-3">
        <label class="form-label m-0" for="prefill">Prefill by title (optional)</label>
        <input class="form-control" id="prefill" placeholder="e.g., Call of Duty: Black Ops II">
      </div>
      <div class="d-flex gap-2">
        <button id="fetchBtn" class="btn btn-primary">Fetch Candidates</button>
        <button id="runSyncBtn" class="btn btn-warning">Run Sync (auto)</button>
        <button id="prefillBtn" class="btn btn-outline-light">Prefill: Mass Effect 2</button>
        <a href="/1hnd/gametracker/auth/games.php" class="btn btn-outline-secondary ms-auto">Back to Games</a>
      </div>
      <div id="status" class="mt-3 dim">Idle.</div>
    </div>

    <div id="results" class="hidden panel">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <label class="form-check-label me-2"><input type="checkbox" id="toggleAll" class="form-check-input"> Select all</label>
          <span id="selCount" class="badge bg-info badge-mini">0 selected</span>
        </div>
        <button id="approveBtn" class="btn btn-success">Approve & Save</button>
      </div>

      <div id="cards" class="row g-3"></div>

      <div class="sticky-actions d-flex justify-content-between align-items-center mt-3">
        <div class="dim">Approve saves selected characters to your database for this game.</div>
        <div>
          <span id="selCountBottom" class="badge bg-info badge-mini me-2">0 selected</span>
          <button id="approveBtnBottom" class="btn btn-success">Approve & Save</button>
        </div>
      </div>
    </div>

    <div id="finalMsg" class="hidden panel mt-3"></div>
    <div id="syncLogPanel" class="hidden panel mt-3">
      <h5 class="mb-2">Logs</h5>
      <pre id="syncLogs" class="mb-0" style="background:#0f1230;color:#cfd3ff;padding:1rem;border-radius:8px;max-height:320px;overflow:auto;"></pre>
    </div>
  </div>

  <script>
    const BASE = '/1hnd/gametracker';
    const $ = id => document.getElementById(id);

    // prefill from query string
    const qs = new URLSearchParams(location.search);
    if (qs.get('game_id')) $('gameId').value = qs.get('game_id');

    $('prefillBtn').addEventListener('click', () => $('prefill').value = 'Mass Effect 2');

    const setStatus = (msg) => $('status').textContent = msg;

    let lastPayload = { game_id: null, items: [] };

    function renderCards(items) {
      const wrap = $('cards');
      wrap.innerHTML = '';
      items.forEach((it, idx) => {
        const col = document.createElement('div');
        col.className = 'col-12 col-sm-6 col-md-4 col-lg-3';
        col.innerHTML = `
          <div class="card h-100">
            <img class="thumb" src="${it.image_url ? it.image_url : `${BASE}/images/placeholder.png`}" alt="">
            <div class="card-body">
              <div class="name">${it.name}</div>
              <div class="dim mb-2">GB: ${it.guid || it.gb_id || ''}</div>
              <div class="form-check">
                <input class="form-check-input selbox" type="checkbox" id="chk_${idx}" data-idx="${idx}" checked>
                <label class="form-check-label" for="chk_${idx}">Approve</label>
              </div>
            </div>
          </div>`;
        wrap.appendChild(col);
      });

      const updateSel = () => {
        const n = [...document.querySelectorAll('.selbox')].filter(c => c.checked).length;
        $('selCount').textContent = `${n} selected`;
        $('selCountBottom').textContent = `${n} selected`;
      };
      document.querySelectorAll('.selbox').forEach(cb => cb.addEventListener('change', updateSel));
      $('toggleAll').addEventListener('change', (e) => {
        document.querySelectorAll('.selbox').forEach(cb => cb.checked = e.target.checked);
        updateSel();
      });
      updateSel();
    }

    $('fetchBtn').addEventListener('click', async () => {
      const gameId = $('gameId').value.trim();
      const guessTitle = $('prefill').value.trim();
      if (!gameId) { showToast('Enter a game ID first.', 'warning'); return; }

      setStatus('Fetching candidates…');
      $('results').classList.add('hidden');
      $('finalMsg').classList.add('hidden');

      try {
        const res = await fetch(`${BASE}/api/fetch_character_candidates.php`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
          body: JSON.stringify({ game_id: Number(gameId), guess_title: guessTitle || null })
        });
        const data = await res.json();
        if (!res.ok || data.error) {
          setStatus(`Error: ${data.error || res.statusText}`);
          return;
        }
        lastPayload = { game_id: Number(gameId), items: data.items || [] };
        setStatus(`Found ${lastPayload.items.length} candidate(s). Review and approve.`);
        renderCards(lastPayload.items);
        $('results').classList.remove('hidden');
      } catch (e) {
        setStatus('Network/JSON error.');
      }
    });

    // Run full auto sync and show logs inline and in console
    $('runSyncBtn').addEventListener('click', async () => {
      const gameId = $('gameId').value.trim();
      const guessTitle = $('prefill').value.trim();
      if (!gameId) { showToast('Enter a game ID first.', 'warning'); return; }

      setStatus('Running sync (with Wikipedia)…');
      $('finalMsg').classList.add('hidden');
      $('syncLogPanel').classList.add('hidden');

      try {
        const res = await fetch(`${BASE}/api/sync_characters.php`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
          body: JSON.stringify({ game_id: Number(gameId), guess_title: guessTitle || null })
        });
        const data = await res.json();
        console.log('sync_characters response:', data);
        if (!res.ok || data.error) {
          setStatus(`Sync error: ${data.error || res.statusText}`);
          $('finalMsg').classList.remove('hidden');
          $('finalMsg').innerHTML = `<h4 class="mb-2">❌ Sync Error</h4><pre class="mb-0" style="background:#0f1230;color:#cfd3ff;padding:1rem;border-radius:8px;">${JSON.stringify(data, null, 2)}</pre>`;
          return;
        }

        setStatus('Sync complete.');
        $('finalMsg').classList.remove('hidden');
        $('finalMsg').innerHTML = `
          <h4 class="mb-2">✅ Sync Result</h4>
          <pre class="mb-0" style="background:#0f1230;color:#cfd3ff;padding:1rem;border-radius:8px;">${JSON.stringify(data, null, 2)}</pre>
        `;

        // Render logs together in one place and also to console
        const logs = Array.isArray(data.debug_logs) ? data.debug_logs : [];
        if (logs.length) {
          console.group('sync_characters debug_logs');
          logs.forEach((m, i) => console.log(String(i+1).padStart(3,' '), m));
          console.groupEnd();
          $('syncLogPanel').classList.remove('hidden');
          $('syncLogs').textContent = logs.join('\n');
        } else {
          $('syncLogPanel').classList.remove('hidden');
          $('syncLogs').textContent = 'No logs returned.';
        }
      } catch (e) {
        console.error('sync_characters network/JSON error', e);
        setStatus('Network/JSON error during sync.');
      }
    });

    async function approve() {
      if (!lastPayload.game_id || lastPayload.items.length === 0) return;
      const checkedIdx = [...document.querySelectorAll('.selbox')].filter(cb => cb.checked).map(cb => Number(cb.dataset.idx));
      const selected = checkedIdx.map(i => lastPayload.items[i]);

      if (selected.length === 0) { showToast('Select at least one character.', 'warning'); return; }

      setStatus('Saving approved characters to database…');

      const res = await fetch(`${BASE}/api/approve_characters.php`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        body: JSON.stringify({ game_id: lastPayload.game_id, characters: selected })
      });
      const data = await res.json();
      if (!res.ok || data.error) {
        setStatus(`Save error: ${data.error || res.statusText}`);
        return;
      }

      $('results').classList.add('hidden');
      $('finalMsg').classList.remove('hidden');
      $('finalMsg').innerHTML = `
        <h4 class="mb-2">✅ Saved</h4>
        <pre class="mb-0" style="background:#0f1230;color:#cfd3ff;padding:1rem;border-radius:8px;">${JSON.stringify(data, null, 2)}</pre>
        <div class="mt-3">
          <a class="btn btn-outline-light" href="/1hnd/gametracker/games/characters.php?game_id=${lastPayload.game_id}">View public Characters page</a>
        </div>
      `;
      setStatus('Done.');
    }

    $('approveBtn').addEventListener('click', approve);
    $('approveBtnBottom').addEventListener('click', approve);
  </script>
</body>
</html>
