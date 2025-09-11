<x-filament-panels::page>
    <div class="space-y-3">
        @forelse($rows as $r)
            <div class="p-4 rounded border">
                <div class="font-semibold">{{ $r->survey->name }}</div>
                <div class="text-sm text-gray-600">Status: {{ $r->status }}</div>

                @php
                    $cert = \App\Models\Certificate::where('survey_id', $r->survey_id)
                        ->where('user_id', $r->user_id)
                        ->first();
                @endphp

                @if ($cert && !$cert->revoked)
                    <a target="_blank" href="{{ route('certificates.download', $cert) }}"
                        class="mt-2 inline-flex items-center px-3 py-1.5 rounded bg-green-600 text-white">
                        Unduh Sertifikat
                    </a>
                @elseif($r->status === 'approved')
                    <div class="text-sm text-gray-500 mt-2">Sertifikat sedang diproses…</div>
                @endif

            </div>
        @empty
            <div>Tidak ada keikutsertaan survei.</div>
        @endforelse
    </div>
</x-filament-panels::page>
