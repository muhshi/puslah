<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Tugas</title>
    <style>
        /* Exact copy from Word HTML */
        @page {
            size: 595.3pt 841.9pt;
            margin: 51.05pt 70.9pt 1.0cm 72.0pt;
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
            line-height: 107%;
        }
        .text-center {
            text-align: center;
        }
        .bold {
            font-weight: bold;
        }
        .italic {
            font-style: italic;
        }
        /* Match exact Word spacing */
        .p-normal {
            margin-bottom: 0cm;
            line-height: normal;
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
        .f9 {
            font-size: 9pt;
        }
        .f4 {
            font-size: 4pt;
        }
        .f10 {
            font-size: 10pt;
        }
        .f3 {
            font-size: 3pt;
        }
        .f1 {
            font-size: 1pt;
        }
        
        /* A4 Preview Container - ONLY for browser preview */
        .preview-container {
            @if($is_preview)
            max-width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 51.05pt 70.9pt 1cm 72pt;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            @else
            /* Ensure no extra margins in PDF mode */
            margin: 0;
            padding: 0;
            text-align: justify;
            @endif
        }
    </style>
</head>
<body>

<!-- Preview Container - will be ignored by DomPDF -->
<div class="preview-container">

<!-- EXACT structure from Word HTML -->
<p class="text-center p-normal">
    {{-- Units normalized to pt for DomPDF --}}
    <img src="{{ $logoBase64 }}" alt="Logo BPS" 
         style="position:absolute; left:200pt; top:-10pt; width:63pt; height:55pt;">
    <b><span class="f14 arial">&nbsp;</span></b>
</p>

<p class="text-center p-normal">
    <b><span class="f14 arial">&nbsp;</span></b>
</p>

<p class="text-center p-normal">
    <b><span class="f14 arial">&nbsp;</span></b>
</p>

<p class="text-center p-normal">
    <b class="italic"><span class="f14 arial">BADAN PUSAT STATISTIK KABUPATEN DEMAK</span></b>
</p>

<p class="p-normal">
    <span class="f9 arial">&nbsp;</span>
</p>

<p class="text-center p-normal">
    <b><span class="f16 arial">SURAT TUGAS</span></b>
</p>

<p class="text-center" style="margin-left:18pt;">
    <span class="f12 arial">NOMOR: {{ $surat->nomor_surat }}</span>
</p>

<p><span class="f4 arial">&nbsp;</span></p>

<p style="margin-left:99.25pt;text-indent:-99.25pt;line-height:50%;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial">Menimbang<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>:<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;</span>a.<span style="mso-tab-count:1"> &nbsp;</span>bahwa berdasarkan {{ $surat->survey?->dasar_surat ?? '-' }};</span>
</p>

<p style="margin-left:110.25pt;text-indent:-14.2pt;line-height:150%;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm">
    <span class="f12 arial">b.<span style="mso-tab-count:1"> </span>bahwa berdasarkan sebagaimana dimaksud pada huruf a. perlu menugaskan nama tersebut dalam surat tugas ini untuk {{ $surat->keperluan }}.</span>
</p>

<p style="margin-left:120.5pt;text-indent:-120.5pt;line-height:0%;tab-stops:70.9pt 92.15pt 106.35pt 120.5pt">
    <span class="f1 arial">&nbsp;</span>
</p>

<p style="margin-left:120.5pt;text-indent:-120.5pt;line-height:normal;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt 120.5pt">
    <span class="f12 arial">Mengingat<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>:<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;</span>1.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>Undang-Undang Nomor 16 Tahun 1997 tentang Statistik;</span>
</p>

<p style="margin-left:110.5pt;text-indent:-120.5pt;line-height:normal;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt 120.5pt">
    <span class="f12 arial"><span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>2.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>Undang-Undang Nomor 43 Tahun 2009 tentang Badan Pusat Statistik;</span>
</p>

<p style="margin-left:110.25pt;text-indent:-99.25pt;line-height:normal;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial"><span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>3.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>Peraturan Presiden Nomor 86 Tahun 2007 tentang Badan Pusat Statistik;</span>
</p>

<p style="margin-left:110.25pt;text-indent:-99.25pt;line-height:normal;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial"><span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>4.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>Peraturan Pemerintah Nomor 28 Tahun 2012 tentang Pelaksanaan Undang-Undang Nomor 43 Tahun 2009 tentang Kearsipan;</span>
</p>

<p style="margin-left:110.25pt;text-indent:-99.25pt;line-height:normal;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial"><span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>5.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>Peraturan Badan Pusat Statistik Nomor 5 Tahun 2023 tentang Organisasi dan Tata Kerja Badan Pusat Statistik Provinsi dan Badan Pusat Statistik Kabupaten/Kota.</span>
</p>

<p class="text-center" style="margin-left:99.25pt;text-indent:-99.25pt;line-height:0%;tab-stops:70.9pt 3.0cm 99.25pt">
    <b><span class="f3 arial">&nbsp;</span></b>
</p>

<p class="text-center" style="margin-left:99.25pt;text-indent:-99.25pt;line-height:150%;tab-stops:70.9pt 3.0cm 99.25pt">
    <b><span class="f12 arial">Memberi Perintah/Tugas :</span></b>
</p>

<p style="margin-left:120.5pt;text-indent:-120.5pt;line-height:150%;tab-stops:70.9pt 3.0cm 99.25pt 120.5pt">
    <span class="f12 arial">Kepada<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>:<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;</span>Nama<span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>: <b>{{ $surat->user->profile->full_name ?? $surat->user->name }}</b></span>
</p>

<p style="margin-left:120.5pt;text-indent:-120.5pt;line-height:150%;tab-stops:70.9pt 3.0cm 99.25pt 120.5pt">
    <span class="f12 arial"><span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>Jabatan<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>: {{ $surat->jabatan }}</span>
</p>

<p style="margin-left:120.5pt;text-indent:-120.5pt;line-height:10%;tab-stops:70.9pt 92.15pt 106.35pt 120.5pt">
    <span class="f1 arial">&nbsp;</span>
</p>

<p style="margin-left:99.25pt;text-indent:-99.25pt;line-height:150%;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial">Untuk<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>:<span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;</span>1.<span style="mso-tab-count:1">&nbsp;&nbsp;</span>{{ $surat->keperluan }} tanggal {{ $periode }} di {{ $surat->tempat_tugas ?? '-' }}.</span>
</p>

<p style="margin-left:99.25pt;text-indent:-99.25pt;line-height:150%;text-align:justify !important;text-justify:inter-word !important;tab-stops:70.9pt 3.0cm 99.25pt">
    <span class="f12 arial"><span style="mso-tab-count:2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>2. Melaksanakan tugas dengan seksama dan penuh rasa tanggung jawab.</span>
</p>

<p style="margin-left:4.5pt;line-height:10%;margin-bottom:0;">
    <span class="f10 arial">&nbsp;</span>
</p>

<p class="text-center" style="margin-left:238.5pt;line-height:150%;margin-bottom:0;">
    <span class="f12 arial">Demak, {{ $surat->tanggal->translatedFormat('d F Y') }}</span>
</p>

<p class="text-center" style="margin-left:241pt;line-height:150%;margin-bottom:0;">
    <span class="f12 arial">Kepala Badan Pusat Statistik</span>
</p>

<p class="text-center" style="margin-left:241pt;line-height:150%;margin-bottom:0;">
    <span class="f12 arial">Kabupaten Demak,<br><br></span>
</p>

<p class="text-center" style="margin-left:241pt;line-height:10%;margin-bottom:0;">
    <img src="{{ $qrBase64 }}" width="60pt" height="60pt" alt="QR">
</p>

<p class="text-center" style="margin-left:241pt;line-height:10%;margin-bottom:0;">
    <span class="f12 arial">&nbsp;</span>
</p>

<p class="text-center" style="margin-left:241pt;line-height:150%;">
    <b><span class="f12 arial">{{ $surat->signer_name }}</span></b>
</p>

</div><!-- End preview-container -->

</body>
</html>