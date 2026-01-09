<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Sertifikat</title>
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
        {{-- INVALID STATE --}}
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-8 text-center border-t-8 border-red-500">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Sertifikat Tidak Valid</h1>
            <p class="text-gray-500 mb-6">Sertifikat dengan nomor tersebut tidak ditemukan atau telah dicabut.</p>
            <div class="bg-gray-100 rounded-lg p-3 text-sm text-gray-600 font-mono">
                No: {{ $no ?? '-' }}
            </div>
        </div>
    @else
        {{-- VALID STATE --}}
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full overflow-hidden border-t-8 border-green-500">
            {{-- Header Profile --}}
            <div class="p-8 text-center pb-0">
                @php
                    $avatarPath = $cert->user->profile->avatar_path ?? null;
                    $avatarUrl = $avatarPath ? \Illuminate\Support\Facades\Storage::url($avatarPath) : 'https://www.pngfind.com/pngs/m/610-6104451_image-placeholder-png-user-profile-placeholder-image-png.png';
                @endphp

                <div class="relative w-24 h-24 mx-auto mb-4">
                    <img src="{{ $avatarUrl }}" alt="Profile"
                        class="w-full h-full rounded-full object-cover border-4 border-green-100 shadow-sm">
                    <div class="absolute bottom-0 right-0 bg-green-500 text-white rounded-full p-1.5 border-2 border-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </div>
                </div>

                <h1 class="text-xl font-bold text-gray-800 mb-1">
                    {{ $cert->user->profile->full_name ?? $cert->user->name }}
                </h1>
                <p class="text-green-600 font-medium text-sm flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"></path>
                    </svg>
                    Valid & Terverifikasi
                </p>
            </div>

            {{-- Details --}}
            <div class="p-6 space-y-4">
                <div class="text-center">
                    <div class="text-xs text-uppercase text-gray-400 font-semibold tracking-wider mb-1">SURVEI</div>
                    <div class="text-gray-700 font-medium leading-tight">
                        {{ $cert->survey->name }}
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 space-y-3 text-sm">
                    <div class="flex justify-between items-start">
                        <span class="text-gray-500">Nomor Sertifikat</span>
                        <span class="font-mono text-gray-700 font-semibold text-right max-w-[150px] break-words">
                            {{ $cert->certificate_no }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Tanggal Terbit</span>
                        <span class="text-gray-700 font-medium">
                            {{ $cert->issued_at->translatedFormat('d F Y') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400">
                    Sistem Validasi Sertifikat Digital<br>
                    &copy; {{ date('Y') }} Puslah
                </p>
            </div>
        </div>
    @endif

</body>

</html>