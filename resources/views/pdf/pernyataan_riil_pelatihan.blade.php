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
            margin-top: 18px;
        }

        .page-break {
            page-break-before: always;
        }

        .lampiran-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .lampiran-subtitle {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .meta-small {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9.5pt;
        }

        .meta-small td {
            padding: 2px 4px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 14px;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: middle;
        }

        .detail-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }

        .ttd-odd {
            text-align: left;
            padding-left: 8px;
            font-size: 8.5pt;
        }

        .ttd-even {
            text-align: left;
            padding-left: 8px;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>
@php
    $paymentRows = $data['payment_rows'] ?? [];
    $totalTransport = array_sum(array_column($paymentRows, 'transport'));
    $tanggalAkhir = ! empty($data['end_date'])
        ? $data['end_date']->translatedFormat('d F Y')
        : date('d F Y');

    $groupedAsal = [];
    foreach ($paymentRows as $row) {
        $asalKey = trim((string) ($row['asal'] ?? 'Demak')) ?: 'Demak';
        if (! isset($groupedAsal[$asalKey])) {
            $groupedAsal[$asalKey] = [
                'asal' => $asalKey,
                'count' => 0,
                'total' => 0,
            ];
        }
        $groupedAsal[$asalKey]['count']++;
        $groupedAsal[$asalKey]['total'] += (int) ($row['transport'] ?? 0);
    }
@endphp

    <div class="title">PERNYATAAN PENGELUARAN RIIL</div>

    <div>Yang bertanda tangan di bawah ini :</div>

    <table class="identity-table">
        <tr>
            <td class="identity-label">Nama</td>
            <td class="identity-colon">:</td>
            <td>{{ $data['penyelenggara']['name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="identity-label">NIP</td>
            <td class="identity-colon">:</td>
            <td>{{ $data['penyelenggara']['nip'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="identity-label">Jabatan</td>
            <td class="identity-colon">:</td>
            <td>{{ $data['penyelenggara']['title'] ?? 'Penyelenggara Kegiatan' }} ({{ $survey->name ?? 'Pelatihan' }})</td>
        </tr>
        <tr>
            <td class="identity-label">Anggota</td>
            <td class="identity-colon">:</td>
            <td>Terlampir ({{ count($paymentRows) }} orang)</td>
        </tr>
    </table>

    <div class="paragraph">
        Berdasarkan Surat Tugas Nomor: {{ $data['nomor_surat_tugas'] ?: '-' }} Tanggal {{ $data['tanggal_surat_tugas'] }}
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
            @forelse (array_values($groupedAsal) as $idx => $group)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $group['asal'] }} ({{ $group['count'] }} org)</td>
                    <td class="text-center">{{ $data['lokasi'] ?: '-' }}</td>
                    <td class="text-right">Rp {{ number_format($group['total'], 0, ',', '.') }},-</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center">1</td>
                    <td>BPS Kabupaten Demak</td>
                    <td class="text-center">{{ $data['lokasi'] ?: '-' }}</td>
                    <td class="text-right">Rp 0,-</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3" class="text-center"><strong>Jumlah</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($totalTransport, 0, ',', '.') }},-</strong></td>
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
                <span class="sig-name">{{ $data['penyelenggara']['name'] ?? '-' }}</span><br>
                NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
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

    <div class="page-break"></div>

    <div class="lampiran-title">Lampiran Daftar Pengeluaran Riil Transportasi Lokal</div>
    <div class="lampiran-subtitle">{{ $survey->name ?? 'Pelatihan' }}</div>

    <table class="meta-small">
        <tr>
            <td style="width: 150px;">Nomor Surat Tugas</td>
            <td style="width: 12px;">:</td>
            <td>{{ $data['nomor_surat_tugas'] ?: '-' }}</td>
        </tr>
        <tr>
            <td>Waktu Pelaksanaan</td>
            <td>:</td>
            <td>{{ $data['periode'] }} ({{ $data['jumlah_hari'] }} hari)</td>
        </tr>
        <tr>
            <td>Tempat Kegiatan</td>
            <td>:</td>
            <td>{{ $data['lokasi'] ?: '-' }}</td>
        </tr>
    </table>

    <table class="detail-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 5%;">No</th>
                <th rowspan="2" style="width: 24%;">Nama Peserta</th>
                <th rowspan="2" style="width: 17%;">NIK / NIP</th>
                <th rowspan="2" style="width: 14%;">Asal</th>
                <th rowspan="2" style="width: 14%;">Tujuan</th>
                <th rowspan="2" style="width: 12%;">Biaya Transport (Rp)</th>
                <th colspan="2" style="width: 14%;">Tanda Tangan</th>
            </tr>
            <tr>
                <th style="width: 7%;">Ganjil</th>
                <th style="width: 7%;">Genap</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentRows as $i => $row)
                @php $num = $i + 1; @endphp
                <tr>
                    <td class="text-center">{{ $num }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td class="text-center">{{ $row['nik_or_nip'] }}</td>
                    <td>{{ $row['asal'] }}</td>
                    <td>{{ $row['tujuan'] }}</td>
                    <td class="text-right">{{ number_format((int) $row['transport'], 0, ',', '.') }}</td>
                    <td class="ttd-odd">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                    <td class="ttd-even">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Belum ada peserta terdaftar.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="5" class="text-right"><strong>JUMLAH</strong></td>
                <td class="text-right"><strong>{{ number_format($totalTransport, 0, ',', '.') }}</strong></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

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
                <span class="sig-name">{{ $data['penyelenggara']['name'] ?? '-' }}</span><br>
                NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
            </td>
        </tr>
    </table>
</body>
</html>
