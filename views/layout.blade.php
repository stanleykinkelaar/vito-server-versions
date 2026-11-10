<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script>
        if ('{{ $appearance ?? 'system' }}' === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <title>{{ $title }} - {{ config('app.name', 'Vito') }}</title>
    <link rel="icon" href="{{ asset('favicon/favicon-96x96.png') }}" sizes="any" />
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <style>
        :root {
            --bg: oklch(1 0 0); --fg: oklch(0.145 0 0); --card: oklch(1 0 0); --muted: oklch(0.556 0 0);
            --border: oklch(0.922 0 0); --subtle: oklch(0.97 0 0); --primary: #5a5bc5;
            --warning-bg: oklch(0.97 0.05 85); --warning-fg: oklch(0.45 0.12 70);
            --danger-bg: oklch(0.96 0.03 25); --danger-fg: oklch(0.5 0.2 25);
            --ok-bg: oklch(0.96 0.04 150); --ok-fg: oklch(0.45 0.12 150);
        }
        html.dark {
            --bg: oklch(0.145 0 0); --fg: oklch(0.985 0 0); --card: oklch(0.18 0 0); --muted: oklch(0.708 0 0);
            --border: oklch(0.269 0 0); --subtle: oklch(0.205 0 0);
            --warning-bg: oklch(0.3 0.06 75); --warning-fg: oklch(0.88 0.12 85);
            --danger-bg: oklch(0.3 0.08 25); --danger-fg: oklch(0.85 0.1 25);
            --ok-bg: oklch(0.3 0.06 150); --ok-fg: oklch(0.85 0.1 150);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 14px/1.5 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        main { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
        nav.crumbs { display: flex; gap: 8px; flex-wrap: wrap; color: var(--muted); font-size: 13px; margin-bottom: 16px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0; }
        h2 { font-size: 15px; font-weight: 600; margin: 0; padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .muted { color: var(--muted); }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .button { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 8px; font: inherit; font-weight: 500;
                  border: 1px solid var(--border); background: var(--card); color: var(--fg); cursor: pointer; }
        .button:hover { background: var(--subtle); text-decoration: none; }
        .button.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .button.small { height: 28px; padding: 0 10px; font-size: 13px; }
        .button[disabled] { opacity: .6; cursor: progress; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
        .card .body { padding: 14px 16px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 16px; border-bottom: 1px solid var(--border); vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        th { font-weight: 500; color: var(--muted); font-size: 13px; background: var(--subtle); white-space: nowrap; }
        code, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0; }
        .grid > div { padding: 12px 16px; border-bottom: 1px solid var(--border); }
        .grid dt { color: var(--muted); font-size: 13px; }
        .grid dd { margin: 2px 0 0; font-weight: 500; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 500; white-space: nowrap; background: var(--subtle); }
        .badge.ok { background: var(--ok-bg); color: var(--ok-fg); }
        .badge.warning { background: var(--warning-bg); color: var(--warning-fg); }
        .badge.danger { background: var(--danger-bg); color: var(--danger-fg); }
        .notice { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; border: 1px solid var(--border); }
        .notice.ok { background: var(--ok-bg); color: var(--ok-fg); border-color: transparent; }
        .notice.warning { background: var(--warning-bg); color: var(--warning-fg); border-color: transparent; }
        .notice.danger { background: var(--danger-bg); color: var(--danger-fg); border-color: transparent; }
        .notice pre { margin: 8px 0 0; white-space: pre-wrap; font-size: 12px; }
        .empty { padding: 32px 16px; text-align: center; color: var(--muted); }
    </style>
</head>
<body>
<main>
    @if (session('success'))
        <div class="notice ok">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="notice danger">Check failed<pre>{{ session('error') }}</pre></div>
    @endif

    @yield('content')
</main>
<script>
    document.querySelectorAll('form[data-check]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button');
            button.disabled = true;
            button.textContent = 'Checking…';
        });
    });
</script>
</body>
</html>
