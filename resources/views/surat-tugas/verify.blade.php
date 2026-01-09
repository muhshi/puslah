<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Surat Tugas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

    @if (!$ok)
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-8 text-center border-t-8 border-red-500">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Dokumen Tidak Valid</h1>
            <p class="text-gray-500">Surat Tugas tidak ditemukan atau tautan rusak.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden border-t-8 border-blue-600">
            <div class="bg-blue-50 p-6 text-center border-b border-blue-100">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900">Surat Tugas Valid</h1>
                <p class="text-blue-600 font-medium text-sm">Dokumen Asli & Terverifikasi</p>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Nomor Surat</label>
                    <div class="font-mono text-gray-800 font-semibold bg-gray-50 p-2 rounded border border-gray-200">
                        {{ $surat->nomor_surat }}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Tanggal</label>
                        <div class="text-gray-700 font-medium">{{ $surat->tanggal->translatedFormat('d M Y') }}</div>
                    </div>
                    <div>
                        <label
                            class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Penandatangan</label>
                        <div class="text-gray-700 font-medium">{{ $surat->signer_name }}</div>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 mt-4">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Pegawai
                        Ditugaskan</label>
                    <div class="flex items-center gap-3">
                        @php
                            $avatar = $surat->user->profile->avatar_path ?? null;
                            $avatarUrl = $avatar ? \Illuminate\Support\Facades\Storage::url($avatar) : 'https://www.pngfind.com/pngs/m/610-6104451_image-placeholder-png-user-profile-placeholder-image-png.png';
                        @endphp
                        <img src="{{ $avatarUrl }}" class="w-10 h-10 rounded-full object-cover bg-gray-200">
                        <div>
                            <div class="text-gray-900 font-bold leading-tight">
                                {{ $surat->user->profile->full_name ?? $surat->user->name }}</div>
                            <div class="text-gray-500 text-sm">{{ $surat->jabatan }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Keperluan</label>
                    <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded">{{ $surat->keperluan }}</p>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-3 text-center text-xs text-gray-400">
                Puslah Verification System
            </div>
        </div>
    @endif
</body>

</html>