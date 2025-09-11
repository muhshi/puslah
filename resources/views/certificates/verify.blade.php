<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial;
            padding: 24px
        }

        .ok {
            color: green
        }

        .bad {
            color: #b91c1c
        }
    </style>
</head>

<body>
    @if (!$ok)
        <h2 class="bad">Sertifikat tidak valid</h2>
        <p>Nomor: {{ $no }}</p>
    @else
        <h2 class="ok">Sertifikat valid</h2>
        <p>Nomor: <b>{{ $cert->certificate_no }}</b></p>
        <p>Nama: <b>{{ $cert->user->profile->full_name ?? $cert->user->name }}</b></p>
        <p>Survey: <b>{{ $cert->survey->name }}</b></p>
        <p>Diterbitkan: {{ $cert->issued_at->format('d M Y H:i') }}</p>
    @endif
</body>

</html>
