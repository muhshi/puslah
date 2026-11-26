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
            width: 48%;
            margin-left: auto;
            text-align: left;
        }

        .sig-space {
            height: 64px;
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
            'pangkat_golongan' => $data['penyelenggara']['pangkat_golongan'] ?? '-',
            'jabatan' => $data['penyelenggara']['title'] ?? 'Peserta',
            'role_pelatihan' => 'Peserta',
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
        $hasPangkat = filled($row['pangkat_golongan'] ?? null) && trim((string) $row['pangkat_golongan']) !== '-';
        $stNomor = $row['surat_tugas_nomor'] ?: ($data['nomor_surat_tugas'] ?: '-');
        $stTanggal = $row['surat_tugas_tanggal'] ?? $data['tanggal_surat_tugas'];
    @endphp
    <div class="statement-page">
        <div class="title">SURAT PERNYATAAN</div>

        <div class="intro">Yang bertanda tangan di bawah ini :</div>

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
            @if ($hasPangkat)
                <tr>
                    <td class="identity-label">Pangkat/Gol</td>
                    <td class="identity-colon">:</td>
                    <td>{{ $row['pangkat_golongan'] }}</td>
                </tr>
            @else
                <tr>
                    <td class="identity-label">Jabatan</td>
                    <td class="identity-colon">:</td>
                    <td>{{ $row['jabatan'] ?: ($row['role_pelatihan'] ?? 'Peserta') }}</td>
                </tr>
            @endif
        </table>

        <div class="paragraph">
            menerangkan bahwa dalam rangka melaksanakan perjalanan dinas dalam kota untuk melaksanakan tugas kedinasan sesuai
            surat tugas nomor {{ $stNomor }} tanggal {{ $stTanggal }}
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
                <u>{{ $displayName }}</u><br>
                @if ($idValue !== '-')
                    {{ $idLabel }}. {{ $idValue }}
                @endif
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
