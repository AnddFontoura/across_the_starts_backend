<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') · Across the Stars</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, 'Segoe UI', Roboto, sans-serif;
            background: #0b0f18;
            color: #e6eefc;
        }
        a { color: #7fb2ff; }
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.8rem 1.4rem; background: #10182a;
            border-bottom: 1px solid rgba(120,160,220,.18);
        }
        .brand { font-weight: 800; letter-spacing: .5px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 1.5rem 1.4rem; }
        .card {
            background: #0e1524; border: 1px solid rgba(120,160,220,.15);
            border-radius: 12px; padding: 1.2rem; margin-bottom: 1.2rem;
        }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        h2 { font-size: 1.1rem; margin: 0 0 .8rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid rgba(120,160,220,.12); }
        th { color: #93a6c6; font-weight: 600; }
        label { display: block; font-size: .8rem; color: #9fb2cf; margin-bottom: .3rem; }
        input, select, textarea {
            width: 100%; padding: .5rem .6rem; border-radius: 8px;
            border: 1px solid rgba(120,160,220,.25); background: #0e1420; color: #eaf2ff;
        }
        .grid { display: grid; gap: .8rem; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .btn {
            display: inline-block; padding: .55rem 1rem; border: none; border-radius: 8px;
            background: linear-gradient(135deg,#4fc3f7,#2a7fd8); color: #04101f;
            font-weight: 700; cursor: pointer; text-decoration: none;
        }
        .btn-sm { padding: .3rem .6rem; font-size: .8rem; }
        .btn-ghost { background: transparent; border: 1px solid rgba(120,160,220,.3); color: #cfe0ff; }
        .btn-danger { background: #b3453a; color: #fff; }
        .status { background: #1c3a26; border: 1px solid #2f7d4e; color: #b9f5cc; padding: .6rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .error { color: #ff9090; font-size: .85rem; margin: .3rem 0; }
        .swatch { display:inline-block; width:14px; height:14px; border-radius:4px; vertical-align:middle; }
        .muted { color: #7f93b3; }
        form.inline { display: inline; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">Across the Stars — Painel</div>
        @auth
            <div>
                <span class="muted">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                    @csrf
                    <button class="btn btn-ghost btn-sm">Sair</button>
                </form>
            </div>
        @endauth
    </div>

    <div class="container">
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
