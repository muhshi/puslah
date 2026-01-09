<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Surat Tugas</title>
    <style>
        @page {
            size: 595.3pt 841.9pt;
            /* Reduced left margin from 72pt to 54pt (approx 1.9cm) */
            margin: 51.05pt 70.9pt 1.0cm 54.0pt;
        }

        body {
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            margin: 0;
            padding: 0;
        }

        p {
            margin-top: 0cm;
            margin-right: 0cm;
            margin-bottom: 8pt;
            margin-left: 0cm;
            line-height: normal;
        }

        .text-center {
            text-align: center;
        }

        .text-justify {
            text-align: justify;
            text-justify: inter-word;
        }

        .bold {
            font-weight: bold;
        }

        .italic {
            font-style: italic;
        }

        .arial {
            font-family: Arial, sans-serif;
        }

        .f14 {
            font-size: 14pt;
        }

        .f16 {
            font-size: 16pt;
        }

        .f12 {
            font-size: 12pt;
        }

        .f10 {
            font-size: 10pt;
        }

        .f9 {
            font-size: 9pt;
        }

        /* Table Layout Helpers */
        table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 0;
        }

        td {
            vertical-align: top;
            padding: 0;
            border: none;
        }

        .label-col {
            width: 85pt;
            /* Menimbang, Mengingat, etc */
        }

        .colon-col {
            width: 15pt;
            text-align: center;
        }

        .number-col {
            width: 20pt;
        }

        /* A4 Preview Container - ONLY for browser preview */
        .preview-container {
            @if($is_preview)
                max-width: 210mm;
                min-height: 297mm;
                margin: 20px auto;
                /* Match @page margins */
                padding: 51.05pt 70.9pt 1cm 54pt;
                background: white;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            @else margin: 0;
                padding: 0;
            @endif
        }
    </style>
</head>

<body>

    <div class="preview-container">
        {{-- Header Logo --}}
        <div class="text-center" style="margin-bottom: 15pt;">
            <img src="{{ $logoBase64 }}" alt="Logo BPS" style="width:63pt; height:55pt; display: inline-block;">
            <br>
            <div style="margin-top: 5pt;">
                <b class="italic"><span class="f14 arial">BADAN PUSAT STATISTIK KABUPATEN DEMAK</span></b>
            </div>
        </div>

        <p class="p-normal" style="margin-bottom: 0pt;">
            <span class="f9 arial">&nbsp;</span>
        </p>

        <div class="text-center">
            <p class="p-normal" style="margin-bottom: 0;"><b><span class="f16 arial">SURAT TUGAS</span></b></p>
            <p class="p-normal">
                <span class="f12 arial">NOMOR: {{ $surat->nomor_surat }}</span>
            </p>
        </div>

        <br>

        {{-- Menimbang --}}
        <table>
            <tr>
                <td class="label-col"><span class="f12 arial">Menimbang</span></td>
                <td class="colon-col"><span class="f12 arial">:</span></td>
                <td class="number-col"><span class="f12 arial">a.</span></td>
                <td class="text-justify"><span class="f12 arial">bahwa berdasarkan
                        {{ $surat->survey?->dasar_surat ?? '-' }};</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">b.</span></td>
                <td class="text-justify"><span class="f12 arial">bahwa berdasarkan sebagaimana dimaksud pada huruf a,
                        perlu menugaskan nama tersebut dalam surat tugas ini untuk {{ $surat->keperluan }}.</span></td>
            </tr>
        </table>

        <div style="height: 10pt;"></div>

        {{-- Mengingat --}}
        <table>
            <tr>
                <td class="label-col"><span class="f12 arial">Mengingat</span></td>
                <td class="colon-col"><span class="f12 arial">:</span></td>
                <td class="number-col"><span class="f12 arial">1.</span></td>
                <td class="text-justify"><span class="f12 arial">Undang-Undang Nomor 16 Tahun 1997 tentang
                        Statistik;</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">2.</span></td>
                <td class="text-justify"><span class="f12 arial">Undang-Undang Nomor 43 Tahun 2009 tentang Badan Pusat
                        Statistik;</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">3.</span></td>
                <td class="text-justify"><span class="f12 arial">Peraturan Presiden Nomor 86 Tahun 2007 tentang Badan
                        Pusat Statistik;</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">4.</span></td>
                <td class="text-justify"><span class="f12 arial">Peraturan Pemerintah Nomor 28 Tahun 2012 tentang
                        Pelaksanaan Undang-Undang Nomor 43 Tahun 2009 tentang Kearsipan;</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">5.</span></td>
                <td class="text-justify"><span class="f12 arial">Peraturan Badan Pusat Statistik Nomor 5 Tahun 2023
                        tentang Organisasi dan Tata Kerja Badan Pusat Statistik Provinsi dan Badan Pusat Statistik
                        Kabupaten/Kota.</span></td>
            </tr>
        </table>

        <br>

        <div class="text-center" style="margin-bottom: 5pt;">
            <b><span class="f12 arial">Memberi Perintah/Tugas :</span></b>
        </div>

        {{-- Kepada --}}
        <table>
            <tr>
                <td class="label-col"><span class="f12 arial">Kepada</span>
                </td>
                <td class="colon-col"><span class="f12 arial">:</span></td>
                <td style="width: 50pt;"><span class="f12 arial">Nama</span></td>
                <td style="width: 10pt;" class="text-center"><span class="f12 arial">:</span></td>
                <td><b><span class="f12 arial">{{ $surat->user->profile->full_name ?? $surat->user->name }}</span></b>
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">Jabatan</span></td>
                <td class="text-center"><span class="f12 arial">:</span></td>
                <td><span class="f12 arial">{{ $surat->jabatan }}</span></td>
            </tr>
        </table>

        <br>

        {{-- Untuk --}}
        <table>
            <tr>
                <td class="label-col"><span class="f12 arial">Untuk</span></td>
                <td class="colon-col"><span class="f12 arial">:</span></td>
                <td class="number-col"><span class="f12 arial">1.</span></td>
                <td class="text-justify"><span class="f12 arial">{{ $surat->keperluan }} tanggal {{ $periode }} di
                        {{ $surat->tempat_tugas ?? '-' }}.</span></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="f12 arial">2.</span></td>
                <td class="text-justify"><span class="f12 arial">Melaksanakan tugas dengan seksama dan penuh rasa
                        tanggung jawab.</span></td>
            </tr>
        </table>

        <br>
        <br>

        {{-- TTD --}}
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;"></td>
                <td class="text-center">
                    <span class="f12 arial">Demak, {{ $surat->tanggal->translatedFormat('d F Y') }}</span><br>
                    <span class="f12 arial">Kepala Badan Pusat Statistik</span><br>
                    <span class="f12 arial">Kabupaten Demak,</span><br>
                    <br>
                    <img src="{{ $qrBase64 }}" width="60pt" height="60pt" alt="QR"><br>
                    <br>
                    <b><span class="f12 arial">{{ $surat->signer_name }}</span></b>
                </td>
            </tr>
        </table>

    </div>

</body>

</html>