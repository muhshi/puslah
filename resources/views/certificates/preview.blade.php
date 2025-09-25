<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Preview Sertifikat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            background: #f3f4f6;
        }

        .container {
            max-width: 100%;
            padding: 24px;
        }

        /* Kanvas A4 landscape fixed, center */
        .sheet {
            width: 1123px;
            /* ~11.69in * 96dpi */
            height: 794px;
            /* ~8.27in  * 96dpi */
            margin: 0 auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            overflow: hidden;
            position: relative;
            border-radius: 4px;
        }

        .toolbar {
            max-width: 1123px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
        }

        .btn {
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="toolbar">
            <a class="btn" href="{{ route('certificates.download', $certificate) }}">Download PDF</a>
        </div>

        <div class="sheet">
            {{-- kirim flag preview agar .bg absolute, bukan fixed --}}
            @php($preview = true)
            @include('certificates.pdf', ['preview' => true])
        </div>
    </div>
</body>

</html>
