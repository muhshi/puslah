<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Hadir Pelatihan - {{ is_object($survey) ? ($survey->name ?? 'BPS Demak') : ($survey['name'] ?? 'BPS Demak') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm 1.5cm 1.5cm 1.5cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #111827;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #000000;
            margin-bottom: 12px;
            padding-bottom: 6px;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0 4px 6px 4px;
        }

        .header-logo {
            width: 55pt;
            height: 48pt;
        }

        .header-title-main {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .header-title-sub {
            font-size: 8.5pt;
            color: #374151;
            text-align: center;
            margin-top: 2px;
        }

        .document-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin-top: 6px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .document-subtitle {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9pt;
        }

        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
            border: none;
        }

        .meta-label {
            width: 90px;
            font-weight: 500;
            color: #374151;
        }

        .meta-colon {
            width: 12px;
            text-align: center;
        }

        .meta-value {
            font-weight: 600;
            color: #111827;
        }

        /* Attendance Table */
        .content-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 14px;
        }

        .content-table thead {
            display: table-header-group;
        }

        .content-table tr {
            page-break-inside: avoid;
        }

        .content-table th,
        .content-table td {
            border: 1px solid #1f2937;
            padding: 5px 6px;
            vertical-align: middle;
        }

        .content-table th {
            background-color: #f3f4f6;
            color: #111827;
            font-weight: bold;
            text-align: center;
            font-size: 8.5pt;
        }

        .content-table td.text-center {
            text-align: center;
        }

        .content-table td.text-left {
            text-align: left;
        }

        .content-table td.text-right {
            text-align: right;
        }

        .row-signature {
            height: 32px;
            min-height: 32px;
        }

        .ttd-odd {
            text-align: left;
            padding-left: 8px;
            font-weight: normal;
            font-size: 8pt;
            color: #1f2937;
        }

        .ttd-even {
            text-align: right;
            padding-right: 8px;
            font-weight: normal;
            font-size: 8pt;
            color: #1f2937;
        }

        /* Signatures block */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
            page-break-inside: avoid;
        }

        .signature-table td {
            border: none;
            vertical-align: top;
            padding: 2px 4px;
        }

        .class-page {
            page-break-after: always;
        }

        .class-page:last-child {
            page-break-after: auto;
        }
    </style>
</head>
<body>

@php
    $surveyName = is_object($survey) ? ($survey->name ?? '') : ($survey['name'] ?? '');
    $dateCount = is_countable($dates) ? count($dates) : 0;
    if ($dateCount === 0) {
        $dates = [$periode ?: date('d F Y')];
        $dateCount = 1;
    }
@endphp

@foreach($classes as $classIndex => $class)
    @php
        $kelasNama = is_object($class) ? ($class->kelas_nama ?? 'Kelas A') : ($class['kelas_nama'] ?? 'Kelas A');
        $rows = is_object($class) ? ($class->rows ?? []) : ($class['rows'] ?? []);
    @endphp

    <div class="{{ !$loop->last ? 'class-page' : '' }}">
        {{-- Letterhead / Kop Surat --}}
        <table class="header-table">
            <tr>
                <td style="width: 65pt; text-align: left;">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" class="header-logo" alt="Logo BPS">
                    @elseif(file_exists(public_path('images/logo_bps.png')))
                        <img src="{{ public_path('images/logo_bps.png') }}" class="header-logo" alt="Logo BPS">
                    @endif
                </td>
                <td>
                    <div class="header-title-main">BADAN PUSAT STATISTIK KABUPATEN DEMAK</div>
                    <div class="header-title-sub">Jl. Sultan Hadiwijaya No. 23 Demak 59515, Telp: (0291) 685456</div>
                    <div class="header-title-sub">Email: bps3321@bps.go.id  |  Website: demakkab.bps.go.id</div>
                </td>
            </tr>
        </table>

        {{-- Document Title --}}
        <div class="document-title">DAFTAR HADIR PESERTA PELATIHAN</div>
        <div class="document-subtitle">{{ $surveyName }}</div>

        {{-- Metadata Pelatihan --}}
        <table class="meta-table">
            <tr>
                <td style="width: 55%;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td class="meta-label">Kegiatan</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $surveyName }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Waktu Pelaksanaan</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $periode }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 45%;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td class="meta-label" style="width: 60px;">Kelas</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $kelasNama }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label" style="width: 60px;">Tempat</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $lokasi }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Attendance Table --}}
        <table class="content-table">
            <thead>
                @if($dateCount <= 1)
                    <tr>
                        <th style="width: 25px;" rowspan="2">No.</th>
                        <th style="width: 160px;" rowspan="2">Nama Peserta</th>
                        <th style="width: 100px;" rowspan="2">Asal / Instansi</th>
                        <th style="width: 75px;" rowspan="2">Peran</th>
                        <th colspan="2" style="width: 150px;">Tanda Tangan</th>
                    </tr>
                    <tr>
                        <th style="width: 75px; font-size: 7.5pt; font-weight: normal; color: #4b5563;">Ganjil</th>
                        <th style="width: 75px; font-size: 7.5pt; font-weight: normal; color: #4b5563;">Genap</th>
                    </tr>
                @else
                    <tr>
                        <th style="width: 25px;" rowspan="2">No.</th>
                        <th style="width: 150px;" rowspan="2">Nama Peserta</th>
                        <th style="width: 90px;" rowspan="2">Asal / Instansi</th>
                        <th style="width: 65px;" rowspan="2">Peran</th>
                        <th colspan="{{ $dateCount }}">Tanda Tangan Kehadiran</th>
                    </tr>
                    <tr>
                        @foreach($dates as $d)
                            <th style="font-size: 7.5pt; line-height: 1.15;">
                                @if(is_object($d))
                                    {{ $d->translatedFormat('d F') }}<br>{{ $d->translatedFormat('Y') }}
                                @else
                                    {{ $d }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                @endif
            </thead>
            <tbody>
                @forelse($rows as $idx => $row)
                    @php
                        $nomor = $loop->iteration;
                        $nama = is_object($row)
                            ? ($row->user->profile->full_name ?? $row->user->name ?? $row->nama ?? '-')
                            : ($row['user']['profile']['full_name'] ?? $row['user']['name'] ?? $row['nama'] ?? '-');
                        $asal = is_object($row)
                            ? ($row->asal ?? $row->user->profile->kecamatan ?? 'Demak')
                            : ($row['asal'] ?? $row['user']['profile']['kecamatan'] ?? 'Demak');
                        $role = is_object($row)
                            ? ($row->role_pelatihan ?? $row->bertugas_sebagai ?? 'PPL')
                            : ($row['role_pelatihan'] ?? $row['bertugas_sebagai'] ?? 'PPL');
                        $isOdd = ($nomor % 2 !== 0);
                    @endphp
                    <tr class="row-signature">
                        <td class="text-center">{{ $nomor }}</td>
                        <td class="text-left" style="font-weight: 500;">{{ $nama }}</td>
                        <td class="text-left">{{ $asal }}</td>
                        <td class="text-center">{{ $role }}</td>

                        @if($dateCount <= 1)
                            {{-- Single day: 2 subcolumns zig-zag --}}
                            @if($isOdd)
                                <td class="ttd-odd">{{ $nomor }}. ..........</td>
                                <td></td>
                            @else
                                <td></td>
                                <td class="ttd-even">{{ $nomor }}. ..........</td>
                            @endif
                        @else
                            {{-- Multi-day: N columns zig-zag --}}
                            @foreach($dates as $d)
                                <td style="width: {{ round(180 / $dateCount) }}px;" class="{{ $isOdd ? 'ttd-odd' : 'ttd-even' }}">
                                    {{ $nomor }}. ..........
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $dateCount <= 1 ? 6 : (4 + $dateCount) }}" class="text-center" style="padding: 16px; color: #6b7280; font-style: italic;">
                            Belum ada peserta yang didaftarkan pada kelas ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Signatures Footer --}}
        <table class="signature-table">
            <tr>
                <td style="width: 55%;"></td>
                <td style="width: 45%; text-align: center;">
                    <div>Demak, {{ $periode ? (explode(' s.d. ', $periode)[1] ?? $periode) : date('d F Y') }}</div>
                    <div style="margin-top: 3px;">Instruktur Daerah / Penanggung Jawab,</div>
                    <div style="height: 48px;"></div>
                    <div style="font-weight: bold; text-decoration: underline;">{{ $inda_name ?: '..................................................' }}</div>
                    <div style="margin-top: 2px;">NIP. {{ $inda_nip ?: '..................................................' }}</div>
                </td>
            </tr>
        </table>
    </div>
@endforeach

</body>
</html>
