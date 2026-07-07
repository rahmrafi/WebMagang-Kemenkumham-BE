<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebMagang Kemenkumham</title>
    <style>
        :root {
            --bg: #0f172a;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --accent: #38bdf8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at top, rgba(56, 189, 248, 0.2), transparent 38%), linear-gradient(160deg, var(--bg), #020617 70%);
            color: var(--text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            padding: 24px;
        }
        .card {
            width: min(920px, 100%);
            background: rgba(17, 24, 39, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(16px);
        }
        .eyebrow { color: var(--accent); text-transform: uppercase; letter-spacing: 0.18em; font-size: 0.78rem; margin: 0 0 12px; }
        h1 { font-size: clamp(2.4rem, 6vw, 4.6rem); line-height: 0.95; margin: 0; }
        p { max-width: 64ch; color: var(--muted); line-height: 1.7; font-size: 1.02rem; }
        .grid { margin-top: 32px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .item { padding: 18px 20px; border-radius: 18px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(148, 163, 184, 0.12); }
        .item span { display: block; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--accent); margin-bottom: 10px; }
        .item strong { display: block; font-size: 1rem; margin-bottom: 6px; }
        .item small { color: var(--muted); line-height: 1.6; }
    </style>
</head>
<body>
    <main class="card">
        <p class="eyebrow">WebMagang Kemenkumham</p>
        <h1>Backend API siap dipakai untuk frontend.</h1>
        <p>Aplikasi ini menyediakan endpoint publik untuk daftar posisi dan pengajuan, serta endpoint admin untuk mengelola posisi dan memproses submission.</p>
        <section class="grid">
            <div class="item"><span>Public API</span><strong>/api/positions</strong><small>Untuk dropdown posisi pada form frontend.</small></div>
            <div class="item"><span>Submission</span><strong>/api/submit</strong><small>Menerima pengajuan magang atau penelitian beserta berkas ZIP.</small></div>
            <div class="item"><span>Admin API</span><strong>/api/admin/*</strong><small>Login admin memakai bearer token internal dan bisa dipakai dari frontend.</small></div>
        </section>
    </main>
</body>
</html>