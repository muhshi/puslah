<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }} - Bukti Dukung BPS Demak</title>
    <style>
        @page {
            margin: 1.5cm 1.5cm 1.5cm 1.5cm;
            footer: page-footer;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            color: #1e293b;
            line-height: 1.35;
        }
        .header {
            text-align: center;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .header-instansi {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0284c7;
            margin-top: 3px;
            text-transform: uppercase;
        }
        .header-address {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 16px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            font-size: 9.5pt;
            padding: 2.5px 0;
            vertical-align: top;
        }
        .meta-label {
            width: 25%;
            font-weight: bold;
            color: #334155;
        }
        .meta-colon {
            width: 2%;
            text-align: center;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 6px;
            border-left: 3px solid #0284c7;
            padding-left: 6px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            font-size: 9pt;
            vertical-align: top;
        }
        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            color: #0f172a;
        }
        .photo-grid {
            width: 100%;
            margin-top: 10px;
        }
        .photo-card {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            margin-bottom: 14px;
            margin-right: 2%;
            border: 1px solid #cbd5e1;
            padding: 4px;
            background-color: #fff;
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        .photo-img {
            width: 100%;
            height: auto;
            max-height: 180px;
            display: block;
        }
        .photo-caption {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 4px;
            line-height: 1.2;
        }
        .page-break {
            page-break-after: always;
        }
        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            vertical-align: top;
            font-size: 9.5pt;
        }
    </style>
</head>
<body>
    {{-- Header Resmi Kop Surat --}}
    <div class="header">
        <div class="header-instansi">Badan Pusat Statistik Kabupaten Demak</div>
        <div class="header-title">Bukti Dukung Capaian Kinerja (E-Kinerja / KIPAPP)</div>
        <div class="header-address">Jl. Sultan Hadiwijaya No. 27 Demak, Jawa Tengah 59515 &bull; Telepon: (0291) 685324 &bull; Email: bps3321@bps.go.id</div>
    </div>

    {{-- Metadata Pegawai & Butir Kegiatan --}}
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Nama Pegawai</td>
                <td class="meta-colon">:</td>
                <td style="font-weight: bold;">{{ $user->name }}</td>
            </tr>
            <tr>
                <td class="meta-label">NIP</td>
                <td class="meta-colon">:</td>
                <td>{{ $user->nip ?? $user->profile?->nip ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Jabatan / Satker</td>
                <td class="meta-colon">:</td>
                <td>{{ $user->jabatan ?? $user->profile?->jabatan ?? 'Pegawai' }} &bull; BPS Kabupaten Demak</td>
            </tr>
            <tr>
                <td class="meta-label">Butir Kegiatan SKP</td>
                <td class="meta-colon">:</td>
                <td style="font-weight: bold; color: #0284c7;">{{ $report->title }}</td>
            </tr>
            <tr>
                <td class="meta-label">Total Volume Bukti</td>
                <td class="meta-colon">:</td>
                <td>{{ $activities->count() }} Hari Pelaksanaan Kegiatan ({{ $activities->sum(fn($a) => $a->attachments->count()) }} Lampiran Berkas)</td>
            </tr>
            @if($report->description)
            <tr>
                <td class="meta-label">Uraian / Keterangan</td>
                <td class="meta-colon">:</td>
                <td>{{ $report->description }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Matriks Tabel Kegiatan Harian --}}
    <div class="section-title">I. Matriks Kronologis Pelaksanaan Tugas</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 14%;">Tanggal</th>
                <th style="width: 18%;">Surat Tugas</th>
                <th>Uraian Narasi Kegiatan Resmi</th>
                <th style="width: 8%;">Foto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $index => $act)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td style="text-align: center; white-space: nowrap;">
                    {{ \Carbon\Carbon::parse($act->activity_date)->translatedFormat('d M Y') }}
                </td>
                <td style="font-size: 8pt;">
                    @if($act->suratTugas)
                        No. {{ $act->suratTugas->nomor_surat_tugas ?: 'DRAFT' }}
                    @else
                        <span style="color: #64748b;">Rutin / Mandiri</span>
                    @endif
                </td>
                <td>{{ $act->final_content ?: $act->raw_content }}</td>
                <td style="text-align: center;">
                    {{ $act->attachments->count() }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">
                    Belum ada rekaman kegiatan yang tercantum.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Lampiran Dokumentasi Foto Berstempel Fisik --}}
    @php
        $allAttachments = $activities->flatMap->attachments->filter(function ($att) {
            return !str_ends_with(strtolower($att->file_path), '.pdf');
        });
    @endphp

    @if($allAttachments->isNotEmpty())
        <div class="page-break"></div>
        <div class="section-title">II. Lampiran Foto Dokumentasi (Stempel Watermark Resmi)</div>
        <div class="photo-grid">
            @foreach($allAttachments as $att)
                @php
                    $fullPath = storage_path('app/public/' . $att->file_path);
                @endphp
                @if(file_exists($fullPath))
                    <div class="photo-card">
                        <img src="{{ $fullPath }}" class="photo-img" alt="Dokumentasi">
                        <div class="photo-caption">
                            <strong>{{ $att->dailyActivity?->activity_date ? \Carbon\Carbon::parse($att->dailyActivity->activity_date)->translatedFormat('d F Y') : '' }}</strong>:
                            {{ $att->caption ?: $att->dailyActivity?->final_content ?: 'Dokumentasi pelaksanaan tugas' }}
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Lembar Pengesahan & QR Code Verifikasi Digital --}}
    <table class="signature-table">
        <tr>
            <td style="width: 52%; vertical-align: bottom; padding-bottom: 5px;">
                @if(!empty($qrBase64))
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 75px; vertical-align: middle; padding: 0;">
                                <img src="{{ $qrBase64 }}" style="width: 70px; height: 70px; display: block;" alt="QR Code">
                            </td>
                            <td style="vertical-align: middle; padding-left: 8px; font-size: 7.5pt; color: #475569; line-height: 1.25;">
                                <strong style="color: #0f172a;">VERIFIKASI RESMI PUSLAH BPS</strong><br>
                                Pindai QR-Code untuk memeriksa keaslian berkas bukti dukung secara digital.<br>
                                <span style="font-size: 6.5pt; color: #64748b; font-family: monospace;">UUID: {{ $report->uuid }}</span>
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
            <td style="text-align: center; width: 48%; vertical-align: top;">
                Demak, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
                Pegawai yang bersangkutan,<br>
                <br><br><br>
                <strong><u>{{ $user->name }}</u></strong><br>
                NIP. {{ $user->nip ?? $user->profile?->nip ?? '-' }}
            </td>
        </tr>
    </table>
</body>
</html>
