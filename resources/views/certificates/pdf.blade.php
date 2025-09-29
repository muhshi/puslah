@php
    $fontBase = storage_path('fonts/Montserrat/static');
    $montRegular = $fontBase . '/Montserrat-Regular.ttf';
    $montSemiBold = $fontBase . '/Montserrat-SemiBold.ttf';
    $montBold = $fontBase . '/Montserrat-Bold.ttf';
@endphp

<style>
    @page {
        margin: 0;
    }

    /* Background gambar full-page */
    .bg-img {
        position: {{ !empty($preview) ? 'absolute' : 'fixed' }};
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .page {
        position: relative;
        font-family: DejaVu Sans, sans-serif;
        /* ⛳ FIX utama: jangan height:100% untuk PDF */
        height: {{ !empty($preview) ? '100%' : 'auto' }};
        /* kalau preview, biar canvas penuh; kalau PDF auto agar tak overflow */
        box-sizing: border-box;
        padding: {{ $template->margin_top }}px {{ $template->margin_right }}px {{ $template->margin_bottom }}px {{ $template->margin_left }}px;
        background: transparent;
        z-index: 1;
    }

    /* util positioning */
    .abs {
        position: absolute;
    }

    .box {
        position: absolute;
        line-height: 1.25;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .bold {
        font-weight: 700;
    }

    .semibold {
        font-weight: 600;
    }

    /* font sizes */
    .fs-xxl {
        font-size: 42px;
    }

    .fs-xl {
        font-size: 28px;
    }

    .fs-lg {
        font-size: 20px;
    }

    .fs-sm {
        font-size: 12px;
    }

    .fs-xs {
        font-size: 11px;
    }

    /* HAPUS .anchor-x lama, ganti dengan: */
    .center-abs {
        position: absolute;
        width: 720px;
        left: 50%;
        margin-left: -360px;
        text-align: center;
    }

    /* optional: grid bantu saat PREVIEW */
    {{ !empty($preview)
        ? "
                                                                                                                                                                                                                                                                                                                                                                                                                                    .page{ outline:1px solid rgba(0,0,0,.08); }
                                                                                                                                                                                                                                                                                                                                                                                                                                    .box { outline:1px dashed rgba(0,0,0,.25); }
                                                                                                                                                                                                                                                                                                                                                                                                                                    .grid-helper::before{
                                                                                                                                                                                                                                                                                                                                                                                                                                      content:''; position:absolute; inset:0; z-index:0; pointer-events:none;
                                                                                                                                                                                                                                                                                                                                                                                                                                      background:
                                                                                                                                                                                                                                                                                                                                                                                                                                        linear-gradient(to right, rgba(0,0,0,.05) 1px, transparent 1px),
                                                                                                                                                                                                                                                                                                                                                                                                                                        linear-gradient(to bottom, rgba(0,0,0,.05) 1px, transparent 1px);
                                                                                                                                                                                                                                                                                                                                                                                                                                      background-size: 20px 20px;
                                                                                                                                                                                                                                                                                                                                                                                                                                    }
                                                                                                                                                                                                                                                                                                                                                                                                                                    "
        : '' }} .title {
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 8px
    }

    .name {
        font-size: 36px;
        font-weight: 700;
        margin: 10px 0 6px
    }

    .small {
        color: #555;
        font-size: 12px;
    }

    .row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-top: 28px;
    }

    .sign {
        text-align: center;
        width: 300px;
        margin-left: auto;
    }

    img.sig {
        max-height: 80px;
    }

    .qr {
        position: absolute;
        left: {{ $template->qr_left }}px;
        top: {{ $template->qr_top }}px;
        width: {{ $template->qr_size }}px;
        height: {{ $template->qr_size }}px;
    }
</style>

<style>
    /* tambah ukuran font besar */
    .fs-64 {
        font-size: 64px;
    }

    .fs-40 {
        font-size: 40px;
    }

    .fs-22 {
        font-size: 20px;
    }

    .muted {
        color: #3b5983;
    }

    .bold {
        font-weight: 700;
    }

    .semibold {
        font-weight: 600;
    }

    .box {
        position: absolute;
        line-height: 1.25;
    }

    .right {
        text-align: right;
    }

    .center {
        text-align: center;
    }
</style>
<style>
    @font-face {
        font-family: 'Montserrat';
        src: url('{{ $montRegular }}') format('truetype');
        font-weight: 400;
        font-style: normal;
    }

    @font-face {
        font-family: 'Montserrat';
        src: url('{{ $montSemiBold }}') format('truetype');
        font-weight: 600;
        font-style: normal;
    }

    @font-face {
        font-family: 'Montserrat';
        src: url('{{ $montBold }}') format('truetype');
        font-weight: 700;
        font-style: normal;
    }

    body,
    .page {
        font-family: 'Montserrat', DejaVu Sans, sans-serif;
    }
</style>
<style>
    body,
    .page {
        font-family: 'Montserrat', sans-serif;
    }
</style>

</head>

<body>
    @if ($bgBase64)
        <img class="bg-img" src="{{ $bgBase64 }}" alt="bg">
    @endif

    <div class="page grid-helper">
        <!-- QR (tetap dari template) -->
        <img class="qr" src="{{ $qrBase64 }}">

        <!-- Nomor (di bawah judul, rata kanan) -->
        <div class="box right fs-22 semibold muted" style="top: 210px; left: 540px; width: 500px;">
            Nomor: {{ $no }}
        </div>

        <!-- NAMA (besar, kanan, dengan garis tipis di bawah) -->
        <div class="box right fs-40 bold muted" style="top: 345px; left: 380px; width: 640px;">
            {{ $user->profile->full_name ?? $user->name }}
        </div>

        <!-- Deskripsi peran & nama survei (kanan tengah) -->
        <div class="box right fs-20 muted" style="top: 410px; left: 500px; width: 520px;">
            Petugas <span class="bold">{{ $survey->name }}</span>
        </div>

        <!-- Tanggal pelaksanaan (lebih kecil, di bawahnya) -->
        <div class="box right fs-12 muted" style="top: 475px; left: 660px; width: 360px;">
            {{ optional($survey->start_date)->format('d M Y') }} — {{ optional($survey->end_date)->format('d M Y') }}
        </div>

        <!-- Verifikasi (tepat di bawah QR) -->
        @php $verifTop = $template->qr_top + $template->qr_size + 8; @endphp
        <div class="box fs-11 muted"
            style="top: {{ $verifTop }}px; left: {{ $template->qr_left }}px; width: 300px;">
            {{ $qrUrl }}
        </div>

        <!-- Tanggal & Kota (kanan bawah sebelum tanda tangan) -->
        <div class="box right fs-20 muted" style="top: 540px; left: 710px; width: 310px;">
            {{ $template->city_label }} {{ ($signatureDate ?? $issuedAt)->translatedFormat('d F Y') }}
        </div>

        <!-- Blok tanda tangan (kanan bawah) -->
        <div class="box center" style="top: 565px; left: 700px; width: 450px;">
            <img src="{{ $signQrBase64 }}" alt="QR TTD"
                style="width:95px;height:95px; display:block; margin:0 auto 6px;">
            <div class="bold fs-14">{{ $template->signer_name }}</div>
            <div class="fs-12 muted">{{ $template->signer_title }}</div>
            <div class="fs-xs muted" style="margin-bottom:6px;">
                *Ditandatangani secara elektronik — scan untuk verifikasi
            </div>
        </div>
    </div>

</body>

</html>
