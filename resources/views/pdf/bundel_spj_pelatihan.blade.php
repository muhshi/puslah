<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bundel SPJ {{ $kelasNama }} - {{ $survey->name ?? 'Pelatihan' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.4cm 1.4cm 1.4cm 1.4cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #111827;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .section-page {
            page-break-after: always;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #000000;
            margin-bottom: 10px;
            padding-bottom: 4px;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0 4px 5px 4px;
        }

        .header-logo {
            width: 50pt;
            height: 44pt;
        }

        .header-title-main {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            text-align: center;
        }

        .header-title-sub {
            font-size: 8.5pt;
            color: #374151;
            text-align: center;
            margin-top: 2px;
        }

        .doc-title {
            text-align: center;
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0 2px 0;
            color: #000000;
        }

        .doc-subtitle {
            text-align: center;
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #1f2937;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8.5pt;
        }

        .meta-table td {
            padding: 1.5px 4px;
            vertical-align: top;
        }

        .meta-label {
            width: 110px;
            color: #374151;
        }

        .meta-colon {
            width: 10px;
        }

        .grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.2pt;
            margin-bottom: 12px;
        }

        .grid-table th,
        .grid-table td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: middle;
        }

        .grid-table th {
            background-color: #efefef;
            font-weight: bold;
            text-align: center;
            color: #000000;
        }

        .bpu-table {
            font-size: 7.4pt;
        }

        .bpu-table th,
        .bpu-table td {
            padding: 3.5px 4px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .sig-row-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 8.8pt;
            page-break-inside: avoid;
        }

        .sig-row-table td {
            vertical-align: top;
            text-align: center;
            padding: 2px 6px;
        }

        .sig-gap {
            height: 48px;
        }

        .terbilang {
            font-style: italic;
            font-size: 8.5pt;
            margin-top: -6px;
            margin-bottom: 10px;
        }

        @include('pdf.partials.statement_styles')
    </style>
</head>
<body>
@php
    $dates = $data['dates'] ?? [];
    $dateCount = count($dates);
    $isMultiDay = $dateCount > 1;
    $tanggalAkhir = ! empty($data['end_date'])
        ? $data['end_date']->translatedFormat('d F Y')
        : date('d F Y');
    $indas = $data['indas'] ?? [];
    if (empty($indas)) {
        $indas = [[
            'nama' => $data['first_inda']['nama'] ?? ($data['penyelenggara']['name'] ?? 'Instruktur Daerah'),
            'nip' => $data['first_inda']['nip'] ?? ($data['penyelenggara']['nip'] ?? '-'),
            'asal' => 'BPS Kabupaten Demak',
        ]];
    }
    $dipa = $data['dipa'] ?? [];
    $atkItems = $data['atk_items'] ?? ['Totebag', 'Pulpen', 'Pensil 2B', 'Penghapus', 'Rautan', 'Blocknote'];
    $atkDesc = is_array($atkItems) ? implode(', ', $atkItems) : (string) $atkItems;
    $tahunAnggaran = ! empty($data['start_date']) ? $data['start_date']->format('Y') : date('Y');
@endphp

    {{-- 1. DAFTAR HADIR PESERTA --}}
    <div class="section-page">
        @include('pdf.partials.kop_bps', ['logoBase64' => $logoBase64])
        <div class="doc-title">Daftar Hadir Peserta Pelatihan</div>
        <div class="doc-subtitle">{{ $survey->name ?? 'Pelatihan' }} — {{ $kelasNama }}</div>

        <table class="meta-table">
            <tr><td class="meta-label">Kegiatan</td><td class="meta-colon">:</td><td>{{ $survey->name ?? '-' }}</td></tr>
            <tr><td class="meta-label">Kelas</td><td class="meta-colon">:</td><td><strong>{{ $kelasNama }}</strong> ({{ count($classAttendanceRows) }} peserta)</td></tr>
            <tr><td class="meta-label">Tempat / Lokasi</td><td class="meta-colon">:</td><td>{{ $data['lokasi'] ?: '-' }}</td></tr>
            <tr><td class="meta-label">Waktu Pelaksanaan</td><td class="meta-colon">:</td><td>{{ $data['periode'] }} ({{ $data['jumlah_hari'] }} Hari)</td></tr>
        </table>

        <table class="grid-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 5%;">No.</th>
                    <th rowspan="2" style="width: 28%;">Nama Peserta</th>
                    <th rowspan="2" style="width: 22%;">Asal / Instansi</th>
                    <th rowspan="2" style="width: 11%;">Peran</th>
                    @if (! $isMultiDay)
                        <th colspan="2" style="width: 34%;">Tanda Tangan</th>
                    @else
                        <th colspan="{{ $dateCount }}" style="width: 34%;">Tanda Tangan Kehadiran</th>
                    @endif
                </tr>
                <tr>
                    @if (! $isMultiDay)
                        <th>Ganjil</th>
                        <th>Genap</th>
                    @else
                        @foreach ($dates as $d)
                            <th>{{ is_object($d) ? $d->translatedFormat('d/m') : $d }}</th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($classAttendanceRows as $i => $row)
                    @php $num = $i + 1; @endphp
                    <tr>
                        <td class="text-center" style="height: 22px;">{{ $num }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['asal'] }}</td>
                        <td class="text-center">{{ $row['role_pelatihan'] ?? 'PPL' }}</td>
                        @if (! $isMultiDay)
                            <td class="text-left">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                            <td class="text-left">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                        @else
                            @foreach ($dates as $d)
                                <td class="text-left" style="font-size: 7pt;">{{ $num }}.</td>
                            @endforeach
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + ($isMultiDay ? $dateCount : 2) }}" class="text-center">Belum ada peserta pada kelas ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="sig-row-table">
            <tr>
                <td style="width: 55%;"></td>
                <td style="width: 45%;">
                    Demak, {{ $tanggalAkhir }}<br>
                    Instruktur Daerah / Penanggung Jawab,
                    <div class="sig-gap"></div>
                    <u>{{ $data['first_inda']['nama'] ?? ($data['penyelenggara']['name'] ?? '-') }}</u><br>
                    NIP. {{ $data['first_inda']['nip'] ?? ($data['penyelenggara']['nip'] ?? '-') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- 2. DAFTAR HADIR INDA --}}
    <div class="section-page">
        @include('pdf.partials.kop_bps', ['logoBase64' => $logoBase64])
        <div class="doc-title">Daftar Hadir Instruktur Daerah (INDA)</div>
        <div class="doc-subtitle">{{ $survey->name ?? 'Pelatihan' }} — {{ $kelasNama }}</div>

        <table class="meta-table">
            <tr><td class="meta-label">Kegiatan</td><td class="meta-colon">:</td><td>{{ $survey->name ?? '-' }}</td></tr>
            <tr><td class="meta-label">Tempat / Lokasi</td><td class="meta-colon">:</td><td>{{ $data['lokasi'] ?: '-' }}</td></tr>
            <tr><td class="meta-label">Waktu Pelaksanaan</td><td class="meta-colon">:</td><td>{{ $data['periode'] }} ({{ $data['jumlah_hari'] }} Hari)</td></tr>
        </table>

        <table class="grid-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 6%;">No.</th>
                    <th rowspan="2" style="width: 30%;">Nama Instruktur Daerah</th>
                    <th rowspan="2" style="width: 20%;">NIP</th>
                    <th rowspan="2" style="width: 18%;">Jabatan / Instansi</th>
                    @if (! $isMultiDay)
                        <th colspan="2" style="width: 26%;">Tanda Tangan</th>
                    @else
                        <th colspan="{{ $dateCount }}" style="width: 26%;">Tanda Tangan Kehadiran</th>
                    @endif
                </tr>
                <tr>
                    @if (! $isMultiDay)
                        <th>Ganjil</th>
                        <th>Genap</th>
                    @else
                        @foreach ($dates as $d)
                            <th>{{ is_object($d) ? $d->translatedFormat('d/m') : $d }}</th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($indas as $i => $item)
                    @php $num = $i + 1; @endphp
                    <tr>
                        <td class="text-center" style="height: 24px;">{{ $num }}</td>
                        <td>{{ $item['nama'] }}</td>
                        <td class="text-center">{{ $item['nip'] ?? '-' }}</td>
                        <td>{{ $item['asal'] ?? 'BPS Kabupaten Demak' }}</td>
                        @if (! $isMultiDay)
                            <td class="text-left">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                            <td class="text-left">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                        @else
                            @foreach ($dates as $d)
                                <td class="text-left" style="font-size: 7pt;">{{ $num }}.</td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sig-row-table">
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br>
                    Pejabat Pembuat Komitmen,
                    <div class="sig-gap"></div>
                    <u>{{ $data['ppk']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['ppk']['nip'] ?? '-' }}
                </td>
                <td style="width: 50%;">
                    Demak, {{ $tanggalAkhir }}<br>
                    Penyelenggara Kegiatan,
                    <div class="sig-gap"></div>
                    <u>{{ $data['penyelenggara']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- 3. BUKTI PENERIMAAN UANG TRANSPORT DAN UANG HARIAN --}}
    <div class="section-page">
        <div class="doc-title">Daftar Bukti Penerimaan Uang Transport dan Uang Harian</div>
        <div class="doc-subtitle">{{ $survey->name ?? 'Pelatihan' }} — {{ $kelasNama }} (TA {{ $tahunAnggaran }})</div>

        <table class="meta-table">
            <tr><td class="meta-label">Program</td><td class="meta-colon">:</td><td>{{ $dipa['program'] ?? '-' }}</td></tr>
            <tr><td class="meta-label">Kegiatan</td><td class="meta-colon">:</td><td>{{ $dipa['kegiatan'] ?? '-' }}</td></tr>
            <tr><td class="meta-label">KRO / RO</td><td class="meta-colon">:</td><td>{{ ($dipa['klasifikasi_ro'] ?? '-').' / '.($dipa['rincian_output'] ?? '-') }}</td></tr>
            <tr><td class="meta-label">Komponen / MAK</td><td class="meta-colon">:</td><td>{{ ($dipa['komponen'] ?? '-').' / '.($dipa['mak'] ?? '-') }}</td></tr>
        </table>

        <table class="grid-table bpu-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 4%;">No</th>
                    <th rowspan="2" style="width: 17%;">Nama Penerima</th>
                    <th rowspan="2" style="width: 13%;">NIK / NIP</th>
                    <th rowspan="2" style="width: 14%;">Bank &amp; Rekening</th>
                    <th rowspan="2" style="width: 10%;">Asal → Tujuan</th>
                    <th rowspan="2" style="width: 9%;">Transport (Rp)</th>
                    <th rowspan="2" style="width: 11%;">Uang Harian (Rp × Hr)</th>
                    <th rowspan="2" style="width: 10%;">Total Diterima (Rp)</th>
                    <th colspan="2" style="width: 12%;">Tanda Tangan</th>
                </tr>
                <tr>
                    <th>Ganjil</th>
                    <th>Genap</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classPaymentRows as $i => $row)
                    @php
                        $num = $i + 1;
                        $uhSubtotal = (int) $row['uang_harian'] * (int) $row['hari'];
                    @endphp
                    <tr>
                        <td class="text-center" style="height: 24px;">{{ $num }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td class="text-center">{{ $row['nik_or_nip'] }}</td>
                        <td>
                            <strong>{{ $row['bank'] }}</strong><br>
                            {{ $row['rekening'] }}
                        </td>
                        <td>{{ $row['asal'] }} → {{ $row['tujuan'] }}</td>
                        <td class="text-right">{{ number_format((int) $row['transport'], 0, ',', '.') }}</td>
                        <td class="text-right">
                            {{ number_format($uhSubtotal, 0, ',', '.') }}<br>
                            <span style="color: #4b5563; font-size: 6.5pt;">({{ number_format((int) $row['uang_harian'], 0, ',', '.') }}×{{ $row['hari'] }}hr)</span>
                        </td>
                        <td class="text-right"><strong>{{ number_format((int) $row['total'], 0, ',', '.') }}</strong></td>
                        <td class="text-left">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                        <td class="text-left">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">Belum ada data pembayaran untuk kelas ini.</td>
                    </tr>
                @endforelse
                <tr>
                    <td colspan="7" class="text-right"><strong>JUMLAH ({{ $kelasNama }})</strong></td>
                    <td class="text-right"><strong>{{ number_format($classGrandTotal, 0, ',', '.') }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <div class="terbilang">Terbilang: {{ ucfirst($classTerbilang) }}</div>

        <table class="sig-row-table">
            <tr>
                <td style="width: 33%;">
                    Setuju dibayar:<br>
                    Pejabat Pembuat Komitmen,
                    <div class="sig-gap"></div>
                    <u>{{ $data['ppk']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['ppk']['nip'] ?? '-' }}
                </td>
                <td style="width: 34%;">
                    Lunas dibayar tgl:<br>
                    Bendahara Pengeluaran,
                    <div class="sig-gap"></div>
                    <u>{{ $data['bendahara']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['bendahara']['nip'] ?? '-' }}
                </td>
                <td style="width: 33%;">
                    Demak, {{ $tanggalAkhir }}<br>
                    Pembuat Daftar / Penyelenggara,
                    <div class="sig-gap"></div>
                    <u>{{ $data['penyelenggara']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- 4. LAMPIRAN LPD --}}
    <div class="section-page">
        <div class="doc-title">Lampiran Laporan Pelaksanaan Dinas (LPD) / Pelatihan</div>
        <div class="doc-subtitle">{{ $survey->name ?? 'Pelatihan' }} — {{ $kelasNama }}</div>

        <table class="meta-table">
            <tr><td class="meta-label">Nomor Surat Tugas</td><td class="meta-colon">:</td><td>{{ $data['nomor_surat_tugas'] ?: '-' }}</td></tr>
            <tr><td class="meta-label">Waktu Pelaksanaan</td><td class="meta-colon">:</td><td>{{ $data['periode'] }} ({{ $data['jumlah_hari'] }} Hari)</td></tr>
            <tr><td class="meta-label">Tempat / Lokasi</td><td class="meta-colon">:</td><td>{{ $data['lokasi'] ?: '-' }}</td></tr>
        </table>

        <table class="grid-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 6%;">No.</th>
                    <th rowspan="2" style="width: 28%;">Nama Peserta</th>
                    <th rowspan="2" style="width: 19%;">NIK / NIP</th>
                    <th rowspan="2" style="width: 11%;">Peran</th>
                    <th rowspan="2" style="width: 16%;">Asal</th>
                    <th colspan="2" style="width: 20%;">Tanda Tangan</th>
                </tr>
                <tr>
                    <th>Ganjil</th>
                    <th>Genap</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classPaymentRows as $i => $row)
                    @php $num = $i + 1; @endphp
                    <tr>
                        <td class="text-center" style="height: 22px;">{{ $num }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td class="text-center">{{ $row['nik_or_nip'] }}</td>
                        <td class="text-center">{{ $row['role_pelatihan'] }}</td>
                        <td>{{ $row['asal'] }}</td>
                        <td class="text-left">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                        <td class="text-left">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Belum ada peserta pada kelas ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="sig-row-table">
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br>
                    Pejabat Pembuat Komitmen,
                    <div class="sig-gap"></div>
                    <u>{{ $data['ppk']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['ppk']['nip'] ?? '-' }}
                </td>
                <td style="width: 50%;">
                    Demak, {{ $tanggalAkhir }}<br>
                    Penyelenggara Pelatihan,
                    <div class="sig-gap"></div>
                    <u>{{ $data['penyelenggara']['name'] ?? '-' }}</u><br>
                    NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- 5. TANDA TERIMA ATK (IF INCLUDE_ATK) --}}
    @if (! empty($data['include_atk']))
        <div class="section-page">
            @include('pdf.partials.kop_bps', ['logoBase64' => $logoBase64])
            <div class="doc-title">Tanda Terima Perlengkapan Peserta (ATK) Pelatihan</div>
            <div class="doc-subtitle">{{ $survey->name ?? 'Pelatihan' }} — {{ $kelasNama }}</div>

            <table class="meta-table">
                <tr><td class="meta-label">Kegiatan</td><td class="meta-colon">:</td><td>{{ $survey->name ?? '-' }}</td></tr>
                <tr><td class="meta-label">Waktu Pelaksanaan</td><td class="meta-colon">:</td><td>{{ $data['periode'] }}</td></tr>
                <tr><td class="meta-label">Paket Perlengkapan</td><td class="meta-colon">:</td><td>{{ $atkDesc }}</td></tr>
            </table>

            <table class="grid-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 6%;">No.</th>
                        <th rowspan="2" style="width: 28%;">Nama Peserta</th>
                        <th rowspan="2" style="width: 18%;">Asal</th>
                        <th rowspan="2" style="width: 24%;">Rincian Barang Diterima</th>
                        <th colspan="2" style="width: 24%;">Tanda Tangan</th>
                    </tr>
                    <tr>
                        <th>Ganjil</th>
                        <th>Genap</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classPaymentRows as $i => $row)
                        @php $num = $i + 1; @endphp
                        <tr>
                            <td class="text-center" style="height: 22px;">{{ $num }}</td>
                            <td>{{ $row['nama'] }}</td>
                            <td>{{ $row['asal'] }}</td>
                            <td class="text-center">1 Paket ATK Lengkap</td>
                            <td class="text-left">{{ $num % 2 !== 0 ? $num.'.' : '' }}</td>
                            <td class="text-left">{{ $num % 2 === 0 ? $num.'.' : '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Belum ada peserta pada kelas ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <table class="sig-row-table">
                <tr>
                    <td style="width: 55%;"></td>
                    <td style="width: 45%;">
                        Demak, {{ $tanggalAkhir }}<br>
                        Penyelenggara Pelatihan,
                        <div class="sig-gap"></div>
                        <u>{{ $data['penyelenggara']['name'] ?? '-' }}</u><br>
                        NIP. {{ $data['penyelenggara']['nip'] ?? '-' }}
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- 6. PERNYATAAN PENGELUARAN RIIL (PER INDIVIDU DALAM KELAS INI) --}}
    @include('pdf.partials.pernyataan_riil_pages', [
        'paymentRows' => $classPaymentRows,
        'survey' => $survey,
        'data' => $data,
        'tanggalAkhir' => $tanggalAkhir,
        'isBundled' => true,
    ])

    {{-- 7. SURAT PERNYATAAN TIDAK MENGGUNAKAN KENDARAAN DINAS (PER INDIVIDU DALAM KELAS INI) --}}
    @include('pdf.partials.pernyataan_tidak_kendaraan_pages', [
        'paymentRows' => $classPaymentRows,
        'survey' => $survey,
        'data' => $data,
        'tanggalAkhir' => $tanggalAkhir,
        'isBundled' => true,
    ])
</body>
</html>
