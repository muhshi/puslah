<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pernyataan Pengeluaran Riil - {{ $survey->name ?? 'Pelatihan' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.8cm 2cm 1.8cm 2cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            color: #000000;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        @include('pdf.partials.statement_styles')
    </style>
</head>
<body>
@php
    $paymentRows = $data['payment_rows'] ?? [];
    $tanggalAkhir = ! empty($data['end_date'])
        ? $data['end_date']->translatedFormat('d F Y')
        : date('d F Y');
@endphp

@include('pdf.partials.pernyataan_riil_pages', [
    'paymentRows' => $paymentRows,
    'survey' => $survey,
    'data' => $data,
    'tanggalAkhir' => $tanggalAkhir,
])
</body>
</html>
