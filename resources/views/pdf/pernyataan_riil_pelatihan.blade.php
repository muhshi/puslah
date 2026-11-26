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

        .statement-page {
            page-break-after: always;
        }

        .statement-page:last-child {
            page-break-after: auto;
        }

        .title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 18px;
            letter-spacing: 0.3px;
        }

        .identity-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 14px;
        }

        .identity-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .identity-label {
            width: 110px;
        }

        .identity-colon {
            width: 14px;
        }

        .paragraph {
            text-align: justify;
            margin-bottom: 12px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 14px 0;
            font-size: 10pt;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #000000;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .summary-table th {
            text-align: center;
            font-weight: normal;
        }

        .sub-num-row td {
            text-align: center;
            font-size: 9.5pt;
            padding: 2px 6px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }

        .sig-table td {
            vertical-align: top;
            text-align: center;
            width: 50%;
            padding: 2px 10px;
        }

        .sig-space {
            height: 58px;
        }

        .sig-name {
            text-decoration: underline;
            font-weight: normal;
        }

        .sig-center-block {
            width: 100%;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
@php
    $paymentRows = $data['payment_rows'] ?? [];
    $tanggalAkhir = ! empty($data['end_date'])
        ? $data['end_date']->translatedFormat('d F Y')
        : date('d F Y');

    if (empty($paymentRows)) {
        $paymentRows = [[
            'nama_tanpa_gelar' => $data['penyelenggara']['name'] ?? '-',
            'nama' => $data['penyelenggara']['name'] ?? '-',
            'nip' => $data['penyelenggara']['nip'] ?? '-',
            'nik' => '-',
            'nik_or_nip' => $data['penyelenggara']['nip'] ?? '-',
            'role_pelatihan' => $data['penyelenggara']['title'] ?? 'Peserta',
            'asal' => 'BPS Kabupaten Demak',
            'tujuan' => $data['lokasi'] ?: '-',
            'transport' => 0,
            'surat_tugas_nomor' => $data['nomor_surat_tugas'] ?: '-',
            'surat_tugas_tanggal' => $data['tanggal_surat_tugas'],
        ]];
    }
@endphp

@foreach ($paymentRows as $row)
    @php
        $hasNip = filled($row['nip'] ?? null) && trim((string) $row['nip']) !== '-';
        $hasNik = filled($row['nik'] ?? null) && trim((string) $row['nik']) !== '-';
        $idLabel = $hasNip ? 'NIP' : 'NIK';
        $idValue = $hasNip ? $row['nip'] : ($hasNik ? $row['nik'] : '-');
        $displayName = $row['nama_tanpa_gelar'] ?? ($row['nama'] ?? '-');
        $transport = (int) ($row['transport'] ?? 0);
        $stNomor = $row['surat_tugas_nomor'] ?: ($data['nomor_surat_tugas'] ?: '-');
        $stTanggal = $row['surat_tugas_tanggal'] ?? $data['tanggal_surat_tugas'];
    @endphp
    <div class="statement-page">
        <div class="title">PERNYATAAN PENGELUARAN RIIL</div>

        <div>Yang bertanda tangan di bawah ini :</div>

        <table class="identity-table">
            <tr>
                <td class="identity-label">Nama</td>
                <td class="identity-colon">:</td>
                <td>{{ $displayName }}</td>
            </tr>
            <tr>
                <td class="identity-label">{{ $idLabel }}</td>
                <td class="identity-colon">:</td>
                <td>{{ $idValue }}</td>
            </tr>
            <tr>
                <td class="identity-label">Jabatan</td>
                <td class="identity-colon">:</td>
                <td>{{ $row['role_pelatihan'] ?? 'Peserta' }} {{ $survey->name ?? 'Pelatihan' }}</td>
            </tr>
        </table>

        <div class="paragraph">
            Berdasarkan Surat Tugas Nomor: {{ $stNomor }} Tanggal {{ $stTanggal }}
            dengan ini kami menyatakan dengan sesungguhnya bahwa biaya transport Pulang Pergi (PP) selama {{ $data['jumlah_hari'] }} hari
            Petugas di bawah ini yang tidak dapat diperoleh bukti-bukti pengeluaran sebagai berikut:
        </div>

        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 7%;">No</th>
                    <th style="width: 31%;">Asal/dari</th>
                    <th style="width: 37%;">Tujuan</th>
                    <th style="width: 25%;">Biaya (Rp)</th>
                </tr>
                <tr class="sub-num-row">
                    <td>1</td>
                    <td>2</td>
                    <td>3</td>
                    <td>4</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>{{ $row['asal'] ?: 'Demak' }}</td>
                    <td class="text-center">{{ $data['lokasi'] ?: ($row['tujuan'] ?: '-') }}</td>
                    <td class="text-right">Rp {{ number_format($transport, 0, ',', '.') }},-</td>
                </tr>
                <tr>
                    <td colspan="3" class="text-center"><strong>Jumlah</strong></td>
                    <td class="text-right"><strong>Rp {{ number_format($transport, 0, ',', '.') }},-</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="paragraph">
            Jumlah uang tersebut di atas benar-benar dikeluarkan untuk pelaksanaan dimaksud dan apabila di kemudian hari terdapat
            kelebihan atas pembayaran, kami bersedia untuk menyetorkan kelebihan tersebut ke Kas Negara.
        </div>

        <div class="paragraph">
            Demikian pernyataan ini kami buat dengan sebenarnya, untuk dipergunakan sebagaimana mestinya.
        </div>

        <table class="sig-table">
            <tr>
                <td>
                    Menyetujui,<br>
                    Pejabat Pembuat Komitmen,
                    <div class="sig-space"></div>
                    <span class="sig-name">{{ $data['ppk']['name'] ?? '-' }}</span><br>
                    NIP. {{ $data['ppk']['nip'] ?? '-' }}
                </td>
                <td>
                    Demak, {{ $tanggalAkhir }}<br>
                    Pelaksana perjalanan,
                    <div class="sig-space"></div>
                    <span class="sig-name">{{ $displayName }}</span><br>
                    @if ($idValue !== '-')
                        {{ $idLabel }}. {{ $idValue }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="sig-center-block">
            Mengetahui,<br>
            BPS Kabupaten Demak<br>
            K e p a l a,
            <div class="sig-space"></div>
            <span class="sig-name">{{ $data['kepala']['name'] ?? '-' }}</span><br>
            NIP. {{ $data['kepala']['nip'] ?? '-' }}
        </div>
    </div>
@endforeach
</body>
</html>
