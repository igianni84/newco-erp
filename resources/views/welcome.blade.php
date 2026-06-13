<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.headline') }}</title>

    {{--
        Minimal localized holding page (foundations-money-i18n-flags, task 2.4; design D4;
        debt S3). Every visible string resolves through a __('welcome.*') key authored in
        lang/ (task 2.2) — no hardcoded user-facing copy (CLAUDE.md invariant 12). The
        <html lang> attribute uses the active locale in BCP-47 form (zh_Hans -> zh-Hans).

        It chooses NO frontend stack: no Vite/Tailwind asset pipeline, no web-font directive,
        no SPA, no routing. The consumer/producer frontend is the TanStack ADR's (a later
        Module-S gate); that frontend replaces this page wholesale. Styling is one small block
        of plain inline CSS (system fonts) — deliberately not a framework.
    --}}
    <style>
        :root { color-scheme: light dark; }
        html { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            color: #18181b;
            background: #fafaf9;
        }
        h1 {
            margin: 0;
            font-size: clamp(2rem, 6vw, 3rem);
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        .tagline { margin: 0; font-size: 1.125rem; color: #3f3f46; }
        .notice { margin: 0; max-width: 30rem; line-height: 1.5; color: #71717a; }

        @media (prefers-color-scheme: dark) {
            body { color: #f4f4f5; background: #0c0a09; }
            .tagline { color: #d4d4d8; }
            .notice { color: #a1a1aa; }
        }
    </style>
</head>
<body>
    <h1>{{ __('welcome.headline') }}</h1>
    <p class="tagline">{{ __('welcome.tagline') }}</p>
    <p class="notice">{{ __('welcome.coming_soon') }}</p>
</body>
</html>
