{{-- <!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
        }

        .wrap {
            text-align: center;
            padding: 40px;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            margin-top: 16px;
        }

        .name {
            font-size: 36px;
            font-weight: 700;
            margin: 18px 0;
        }

        .small {
            color: #555;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .sign {
            text-align: center;
            width: 300px;
            margin-left: auto;
        }

        img.sig {
            max-height: 80px;
        }

        img.qr {
            width: 120px;
            height: 120px;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="title">SERTIFIKAT</div>
        <div class="small">Nomor: {{ $no }}</div>

        <p class="small" style="margin-top:24px;">Diberikan kepada</p>
        <div class="name">{{ $user->profile->full_name ?? $user->name }}</div>

        <p class="small">Atas partisipasi pada survei:</p>
        <div style="font-size:20px; font-weight:600">{{ $survey->name }}</div>
        <div class="small">{{ optional($survey->start_date)->format('d M Y') }} —
            {{ optional($survey->end_date)->format('d M Y') }}</div>

        <div class="row">
            <div>
                <img class="qr" src="data:image/png;base64,{{ base64_encode(file_get_contents($qrPath)) }}">
                <div class="small">Verifikasi: {{ route('certificates.verify', ['no' => $no]) }}</div>
            </div>
            <div class="sign">
                @if ($signPath)
                    <img class="sig"
                        src="{{ public_path(str_starts_with($signPath, 'storage') ? str_replace('storage/', 'storage/', $signPath) : $signPath) }}">
                @endif
                <div style="border-top:1px solid #333; margin-top:6px; padding-top:6px;">
                    <div style="font-weight:700">{{ $signerName }}</div>
                    <div class="small">{{ $signerTitle }}</div>
                </div>
                <div class="small" style="margin-top:6px;">Diterbitkan: {{ $issuedAt->format('d M Y') }}</div>
            </div>
        </div>
    </div>
</body>

</html> --}}
