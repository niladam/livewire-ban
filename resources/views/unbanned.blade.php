<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Unbanned</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: #f7f7f8;
            color: #18181b;
            font: 400 15px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            padding: 2rem;
            text-align: center;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 1rem;
        }
        h1 { margin: 0 0 .75rem; font-size: 1.5rem; font-weight: 300; letter-spacing: -.01em; }
        p { margin: 0; color: #52525b; }
        .ip { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #18181b; }
        .meta { margin-top: .75rem; font-size: .8125rem; color: #a1a1aa; }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #fafafa; }
            .card { background: #18181b; border-color: #27272a; }
            p { color: #a1a1aa; }
            .ip { color: #fafafa; }
            .meta { color: #71717a; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Unbanned</h1>
        <p><span class="ip">{{ $ban->ip }}</span> can reach the site again.</p>
        <p class="meta">
            Banned {{ $ban->banned_at->format('Y-m-d H:i') }}
            &middot;
            unbanned {{ $ban->unbanned_at?->format('Y-m-d H:i') }}
        </p>
    </div>
</body>
</html>
