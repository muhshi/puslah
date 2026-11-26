<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Pernyataan Tidak Menggunakan Kendaraan Dinas - {{ $survey->name ?? 'Pelatihan' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 2cm 2.2cm 2cm 2.2cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000000;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

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

@include('pdf.partials.pernyataan_tidak_kendaraan_pages', [
    'paymentRows' => $paymentRows,
    'survey' => $survey,
    'data' => $data,
    'tanggalAkhir' => $tanggalAkhir,
])
</body>
</html>
