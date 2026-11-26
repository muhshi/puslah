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

        .title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 24px;
            letter-spacing: 0.4px;
        }

        .intro {
            margin-bottom: 10px;
            text-indent: 28px;
        }

        .identity-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .identity-table td {
            padding: 3px 4px;
            vertical-align: top;
        }

        .identity-label {
            width: 130px;
        }

        .identity-colon {
            width: 14px;
        }

        .paragraph {
            text-align: justify;
            text-indent: 28px;
            margin-bottom: 14px;
        }

        .sig-right-wrapper {
            width: 100%;
            margin-top: 32px;
        }

        .sig-right-box {
            width: 45%;
            margin-left: auto;
            text-align: left;
        }

        .sig-space {
            height: 64px;
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

        .text-center {
            text-align: center;
        }

        .ttd-cell {
            text-align: left;
            padding-left: 8px;
            font-size: 8.5pt;
        }

        .sig-two-col {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            font-size: 10pt;
        }

        .sig-two-col td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
    </style>
</head>
<body>
@php
    $paymentRows = $data['payment_rows'] ?? [];
    $tanggalAkhir = ! empty($data['end_date'])
        ? $data['end_date']->translatedFormat('d F Y')
        : date('d F Y');
@endphp

    <div class="title">SURAT PERNYATAAN</div>

    <div class="intro">Yang bertanda tangan di bawah ini :</div>

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
            <td class="identity-label">Pangkat/Gol</td>
            <td class="identity-colon">:</td>
            <td>{{ $data['penyelenggara']['pangkat_golongan'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="identity-label">Anggota</td>
            <td class="identity-colon">:</td>
            <td>Terlampir ({{ count($paymentRows) }} orang)</td>
        </tr>
    </table>

    <div class="paragraph">
        menerangkan bahwa dalam rangka melaksanakan perjalanan dinas dalam kota untuk melaksanakan tugas kedinasan sesuai
        surat tugas nomor {{ $data['nomor_surat_tugas'] ?: '-' }} tanggal {{ $data['tanggal_surat_tugas'] }}
        ({{ $survey->name ?? 'Pelatihan' }}), saya benar-benar tidak menggunakan kendaraan dinas pada tanggal {{ $data['periode'] }}.
    </div>

    <div class="paragraph">
        Demikian pernyataan ini kami buat dengan sebenar-benarnya untuk dipergunakan sebagaimana mestinya.
        Apabila terdapat kekeliruan dalam pertanggungjawaban SPD dan mengakibatkan kerugian negara, saya bersedia dituntut sesuai
        peraturan yang berlaku dan mengembalikan biaya transpor lokal yang sudah terlanjur saya terima ke kas negara.
    </div>

    <div class="sig-right-wrapper">
        <div class="sig-right-box">
            Demak, {{ $tanggalAkhir }}<br>
            Pelaksana Perjalanan Dinas<br>
            Dalam Kota
            <div class="sig-space"></div>
            Terlampir
        </div>
    </div>

    <div class="page-break"></div>

    <div class="lampiran-title">Lampiran Surat Pernyataan Tidak Menggunakan Kendaraan Dinas</div>
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
                <th rowspan="2" style="width: 6%;">No</th>
                <th rowspan="2" style="width: 30%;">Nama Peserta</th>
                <th rowspan="2" style="width: 20%;">NIK / NIP</th>
                <th rowspan="2" style="width: 20%;">Asal / Instansi</th>
                <th colspan="2" style="width: 24%;">Tanda Tangan</th>
            </tr>
            <tr>
                <th style="width: 12%;">Ganjil</th>
                <th style="width: 12%;">Genap</th>
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
                    <td class="ttd-cell">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                    <td class="ttd-cell">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Belum ada peserta terdaftar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="sig-two-col">
        <tr>
            <td>
                Mengetahui,<br>
                Pejabat Pembuat Komitmen,
                <div class="sig-space"></div>
                <u>{{ $data['ppk']['name'] ?? '-' }}</u><br>
                NIP. {{ $data['ppk']['nip'] ?? '-' }}
            </td>
            <td>
                Demak, {{ $tanggalAkhir }}<br>
                Pelaksana Perjalanan / Penyelenggara,
                <div class="sig-space"></div>
                <u>{{ $data['penyelenggara']['name'] ?? '-' }}</u><br>
                NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
            </td>
        </tr>
    </table>
</body>
</html>
