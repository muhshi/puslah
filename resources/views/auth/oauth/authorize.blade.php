<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Otorisasi Aplikasi — {{ config('app.name', 'Puslah') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Figtree', sans-serif; }
        body {
            background-color: #f3f4f6;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e5e7eb;
            max-width: 460px;
            width: 100%;
            padding: 2rem;
        }
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            display: block;
        }
        .title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        .subtitle {
            font-size: 0.875rem;
            color: #4b5563;
            margin-top: 0.5rem;
            line-height: 1.4;
        }
        .user-badge {
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .scopes-container {
            margin: 1.5rem 0;
            background-color: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 0.75rem;
            padding: 1rem;
        }
        .scopes-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .scope-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .scope-item:last-child { margin-bottom: 0; }
        .scope-icon {
            width: 16px;
            height: 16px;
            color: #10b981;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.75rem;
        }
        .btn {
            flex: 1;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.5rem;
            cursor: pointer;
            text-align: center;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .btn-secondary:hover {
            background-color: #e5e7eb;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            @if(file_exists(public_path('images/logo_bps.png')))
                <img src="{{ asset('images/logo_bps.png') }}" alt="Logo BPS" class="logo">
            @endif
            <h1 class="title">Permintaan Otorisasi</h1>
            <p class="subtitle">
                Aplikasi <strong>{{ $client->name }}</strong> meminta izin untuk mengakses akun Anda di <strong>{{ config('app.name', 'Puslah') }}</strong>.
            </p>
            <div class="user-badge">{{ $user->name }} ({{ $user->email }})</div>
        </div>

        @if (count($scopes) > 0)
            <div class="scopes-container">
                <div class="scopes-title">Akses yang Diminta:</div>
                @foreach ($scopes as $scope)
                    <div class="scope-item">
                        <svg class="scope-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>{{ $scope->description ?? $scope->id }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="actions">
            <!-- Authorize / Approve -->
            <form method="post" action="{{ route('passport.authorizations.approve') }}" style="flex: 1;">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Izinkan
                </button>
            </form>

            <!-- Deny -->
            <form method="post" action="{{ route('passport.authorizations.deny') }}" style="flex: 1;">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="btn btn-secondary" style="width: 100%;">
                    Tolak
                </button>
            </form>
        </div>
    </div>
</body>
</html>
