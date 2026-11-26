<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Surat Undangan - {{ $suratUndangan->nomor_surat }}</title>
    <style>
        @page {
            margin: 1.5cm 2cm 2cm 2cm;
            size: 215.9mm 330.2mm; /* F4 / Folio standard naskah dinas BPS */
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }

        /* KOP SURAT */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .kop-table td {
            vertical-align: middle;
            padding: 0;
        }
        .kop-left {
            width: 85px;
            text-align: left;
        }
        .kop-left img {
            width: 80px;
            height: auto;
        }
        .kop-center {
            text-align: center;
        }
        .kop-instansi {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }
        .kop-unit {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .kop-alamat {
            font-size: 8.5pt;
            margin: 2px 0 0 0;
            line-height: 1.25;
        }
        .kop-right {
            width: 95px;
            text-align: right;
        }
        .kop-right img {
            max-width: 90px;
            max-height: 60px;
            height: auto;
        }
        .kop-line {
            border-top: 2.5px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin: 4px 0 16px 0;
        }

        /* HEADER INFO SURAT */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 11pt;
        }
        .meta-left {
            width: 60%;
        }
        .meta-right {
            width: 40%;
            text-align: right;
        }

        /* TUJUAN */
        .tujuan-block {
            margin-bottom: 16px;
            line-height: 1.35;
        }

        /* ISI SURAT */
        .isi-surat {
            text-align: justify;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .isi-surat p {
            margin: 0 0 8px 0;
            text-indent: 0;
        }
        .isi-surat ol, .isi-surat ul {
            margin: 4px 0 8px 24px;
            padding: 0;
        }
        .isi-surat li {
            margin-bottom: 4px;
        }
        .isi-surat table {
            border-collapse: collapse;
            margin: 6px 0 10px 20px;
        }
        .isi-surat table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        /* TANDA TANGAN */
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .ttd-table td {
            vertical-align: top;
            padding: 0;
        }
        .ttd-spacer {
            width: 55%;
        }
        .ttd-box {
            width: 45%;
            text-align: left;
            line-height: 1.3;
        }

        /* LAMPIRAN RUJUKAN POJOK KANAN ATAS */
        .lampiran-ref {
            width: 100%;
            margin-bottom: 18px;
        }
        .lampiran-ref-table {
            float: right;
            border-collapse: collapse;
            font-size: 10pt;
            line-height: 1.25;
        }
        .lampiran-ref-table td {
            padding: 1px 4px;
            vertical-align: top;
        }

        .lampiran-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin: 15px 0 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.35;
            clear: both;
        }

        /* TABEL PESERTA / DATA LAMPIRAN */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9.5pt;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .data-table th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .data-table .text-center {
            text-align: center;
        }
        .data-table .text-left {
            text-align: left;
        }
        .class-header {
            background-color: #e6e6e6;
            font-weight: bold;
            padding: 6px;
            text-align: left;
        }

        .img-lampiran {
            max-width: 100%;
            max-height: 22cm;
            height: auto;
            display: block;
            margin: 10px auto;
        }
    </style>
</head>
<body>

    {{-- ==================== HALAMAN 1: SURAT UNDANGAN ==================== --}}
    <table class="kop-table">
        <tr>
            <td class="kop-left">
                @if(!empty($logoBpsBase64))
                    <img src="{{ $logoBpsBase64 }}" alt="Logo BPS">
                @endif
            </td>
            <td class="kop-center">
                <div class="kop-instansi">BADAN PUSAT STATISTIK</div>
                <div class="kop-unit">KABUPATEN DEMAK</div>
                <div class="kop-alamat">{!! nl2br(e($kopSurat->instansi_teks ?? "Jl. Sultan Hadiwijaya No. 23 Demak Telp/Fax : (0291) 685445\nHomepage: http://demakkab.bps.go.id, E-mail : bps3321@bps.go.id")) !!}</div>
            </td>
            <td class="kop-right">
                @if(!empty($logoSecondaryBase64))
                    <img src="{{ $logoSecondaryBase64 }}" alt="Logo Kanan">
                @endif
            </td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <table class="meta-table">
        <tr>
            <td class="meta-left">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 75px;">Nomor</td>
                        <td style="width: 10px;">:</td>
                        <td>{{ $suratUndangan->nomor_surat }}</td>
                    </tr>
                    <tr>
                        <td>Sifat</td>
                        <td>:</td>
                        <td>{{ $suratUndangan->sifat }}</td>
                    </tr>
                    <tr>
                        <td>Lampiran</td>
                        <td>:</td>
                        <td>{{ $suratUndangan->lampiran_teks }}</td>
                    </tr>
                    <tr>
                        <td>Hal</td>
                        <td>:</td>
                        <td style="font-weight: bold;">{{ $suratUndangan->hal }}</td>
                    </tr>
                </table>
            </td>
            <td class="meta-right">
                {{ $suratUndangan->signer_city ?: 'Demak' }}, {{ $suratUndangan->formatted_tanggal }}
            </td>
        </tr>
    </table>

    <div class="tujuan-block">
        {!! nl2br(e($suratUndangan->yth_teks)) !!}
    </div>

    <div class="isi-surat">
        @if(!empty($suratUndangan->paragraf_pembuka))
            <p style="margin-bottom: 8px;">{{ $suratUndangan->paragraf_pembuka }}</p>
        @endif

        {!! $suratUndangan->isi_surat_html !!}

        <p style="margin-top: 12px;">
            Demikian disampaikan, untuk dapat dipedomani dan dilaksanakan. Atas perhatiannya diucapkan terima kasih.
        </p>
    </div>

    <table class="ttd-table">
        <tr>
            <td class="ttd-spacer"></td>
            <td class="ttd-box">
                <div>Kepala Badan Pusat Statistik</div>
                <div>Kabupaten Demak,</div>
                <div style="height: 65px;"></div>
                <div style="font-weight: bold; text-decoration: underline;">{{ $suratUndangan->signer_name ?: 'Kepala BPS Demak' }}</div>
            </td>
        </tr>
    </table>

    {{-- ==================== LAMPIRAN 1: DAFTAR PESERTA ==================== --}}
    @if($suratUndangan->sertakan_lampiran_peserta)
        <div class="page-break"></div>

        <div class="lampiran-ref">
            <table class="lampiran-ref-table">
                <tr>
                    <td colspan="3" style="font-weight: bold;">Lampiran 1 Surat Kepala BPS Kabupaten Demak</td>
                </tr>
                <tr>
                    <td style="width: 55px;">Nomor</td>
                    <td style="width: 8px;">:</td>
                    <td>{{ $suratUndangan->nomor_surat }}</td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>:</td>
                    <td>{{ $suratUndangan->formatted_tanggal }}</td>
                </tr>
            </table>
        </div>

        <div class="lampiran-title">
            DAFTAR PESERTA {{ strtoupper($suratUndangan->survey->name ?? $suratUndangan->hal) }}<br>
            KABUPATEN DEMAK TAHUN {{ $suratUndangan->tanggal_surat ? $suratUndangan->tanggal_surat->format('Y') : date('Y') }}
        </div>

        @php
            $cols = $suratUndangan->kolom_lampiran_peserta ?? ['nip', 'asal_kecamatan', 'sobat_id', 'email'];
            $colCount = 3
                + (in_array('nip', $cols) ? 1 : 0)
                + (in_array('asal_kecamatan', $cols) ? 1 : 0)
                + (in_array('sobat_id', $cols) ? 1 : 0)
                + (in_array('email', $cols) ? 1 : 0)
                + (in_array('no_hp', $cols) ? 1 : 0);
        @endphp

        @foreach($pesertaPerKelas as $namaKelas => $pesertaList)
            <table class="data-table">
                @if($isMultiClass)
                    <thead>
                        <tr>
                            <th colspan="{{ $colCount }}" class="class-header">
                                KELAS: {{ strtoupper($namaKelas) }}
                            </th>
                        </tr>
                    </thead>
                @endif
                <thead>
                    <tr>
                        <th style="width: 35px;">No</th>
                        <th>Nama Peserta</th>
                        @if(in_array('nip', $cols))
                            <th style="width: 120px;">NIP</th>
                        @endif
                        <th>Bertugas Sebagai</th>
                        @if(in_array('asal_kecamatan', $cols))
                            <th>Asal Kecamatan / Alamat</th>
                        @endif
                        @if(in_array('sobat_id', $cols))
                            <th style="width: 80px;">Sobat ID</th>
                        @endif
                        @if(in_array('email', $cols))
                            <th>Email</th>
                        @endif
                        @if(in_array('no_hp', $cols))
                            <th style="width: 95px;">No HP / Kontak</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($pesertaList as $idx => $p)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td class="text-left" style="font-weight: 500;">{{ $p['nama'] }}</td>
                            @if(in_array('nip', $cols))
                                <td class="text-center">{{ $p['nip'] ?: '-' }}</td>
                            @endif
                            <td class="text-center">{{ $p['jabatan'] }}</td>
                            @if(in_array('asal_kecamatan', $cols))
                                <td class="text-left">{{ $p['asal_kecamatan'] ?: '-' }}</td>
                            @endif
                            @if(in_array('sobat_id', $cols))
                                <td class="text-center">{{ $p['sobat_id'] ?: '-' }}</td>
                            @endif
                            @if(in_array('email', $cols))
                                <td class="text-left">{{ $p['email'] ?: '-' }}</td>
                            @endif
                            @if(in_array('no_hp', $cols))
                                <td class="text-center">{{ $p['no_hp'] ?: '-' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $colCount }}" class="text-center" style="font-style: italic;">
                                Tidak ada peserta terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @endif

    {{-- ==================== LAMPIRAN 2: JADWAL KEGIATAN ==================== --}}
    @if($suratUndangan->sertakan_lampiran_jadwal)
        <div class="page-break"></div>

        <div class="lampiran-ref">
            <table class="lampiran-ref-table">
                <tr>
                    <td colspan="3" style="font-weight: bold;">Lampiran 2 Surat Kepala BPS Kabupaten Demak</td>
                </tr>
                <tr>
                    <td style="width: 55px;">Nomor</td>
                    <td style="width: 8px;">:</td>
                    <td>{{ $suratUndangan->nomor_surat }}</td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>:</td>
                    <td>{{ $suratUndangan->formatted_tanggal }}</td>
                </tr>
            </table>
        </div>

        <div class="lampiran-title">
            JADWAL {{ strtoupper($suratUndangan->survey->name ?? $suratUndangan->hal) }}<br>
            KABUPATEN DEMAK TAHUN {{ $suratUndangan->tanggal_surat ? $suratUndangan->tanggal_surat->format('Y') : date('Y') }}
        </div>

        @if($suratUndangan->mode_jadwal === 'upload' && !empty($jadwalImageBase64))
            <div style="text-align: center; margin-top: 15px;">
                <img src="{{ $jadwalImageBase64 }}" class="img-lampiran" alt="Jadwal Kegiatan">
            </div>
        @else
            @php
                $schedulesByDay = $suratUndangan->schedules->groupBy(function($s) {
                    $tgl = $s->tanggal ? \Carbon\Carbon::parse($s->tanggal)->locale('id')->translatedFormat('d F Y') : '-';
                    return ($s->hari ? $s->hari . ', ' : '') . $tgl;
                });
            @endphp

            @foreach($schedulesByDay as $dayLabel => $items)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th colspan="{{ $suratUndangan->tampilkan_durasi_jadwal ? 6 : 5 }}" class="class-header">
                                HARI / TANGGAL: {{ strtoupper($dayLabel) }}
                            </th>
                        </tr>
                        <tr>
                            <th style="width: 35px;">No</th>
                            <th style="width: 110px;">Waktu (WIB)</th>
                            @if($suratUndangan->tampilkan_durasi_jadwal)
                                <th style="width: 55px;">Durasi</th>
                            @endif
                            <th>Materi / Aktivitas</th>
                            <th style="width: 40px;">JP</th>
                            <th style="width: 140px;">Metode / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $item)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td class="text-center">{{ $item->waktu_mulai ?: '' }} - {{ $item->waktu_selesai ?: '' }}</td>
                                @if($suratUndangan->tampilkan_durasi_jadwal)
                                    <td class="text-center">{{ $item->formatted_duration }}</td>
                                @endif
                                <td class="text-left" style="font-weight: 500;">{{ $item->materi }}</td>
                                <td class="text-center">{{ $item->jp ?: '-' }}</td>
                                <td class="text-center">
                                    @if($item->metode && $item->keterangan)
                                        {{ $item->metode }} ({{ $item->keterangan }})
                                    @else
                                        {{ $item->metode ?: ($item->keterangan ?: '-') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $suratUndangan->tampilkan_durasi_jadwal ? 6 : 5 }}" class="text-center" style="font-style: italic;">
                                    Belum ada rincian jadwal.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endforeach
        @endif
    @endif

</body>
</html>
