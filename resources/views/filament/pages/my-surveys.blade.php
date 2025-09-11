<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- KANAN (di mobile diletakkan di atas): Urutkan & Filter --}}
        <div class="space-y-6 md:col-start-3 md:row-start-1">
            <x-filament::section heading="Urutkan">
                <div class="space-y-3">
                    <select wire:model.live="sortBy"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        <option value="surveys.start_date">Tanggal Mulai</option>
                        <option value="surveys.end_date">Tanggal Selesai</option>
                        <option value="surveys.name">Nama</option>
                    </select>

                    <select wire:model.live="sortDir"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        <option value="desc">Descending (Z–A)</option>
                        <option value="asc">Ascending (A–Z)</option>
                    </select>
                </div>
            </x-filament::section>

            <x-filament::section heading="Filter">
                <div class="space-y-3">
                    <select wire:model.live="year"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        <option value="">Semua tahun…</option>
                        @foreach (range(now()->year, now()->year - 5) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </x-filament::section>
        </div>

        {{-- KIRI (konten utama): List --}}
        <div class="md:col-span-2 md:col-start-1 md:row-start-1 space-y-4">
            <div class="flex items-center gap-3">
                <x-filament::input.wrapper class="w-full">
                    <x-filament::input wire:model.live.debounce.500ms="search"
                        placeholder="Cari berdasar nama survei…" />
                </x-filament::input.wrapper>

                <select wire:model.live="perPage"
                    class="w-28 rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                </select>
            </div>

            @forelse ($this->rows as $r)
                @php
                    $survey = $r->survey;
                    $cert = \App\Models\Certificate::where('survey_id', $r->survey_id)
                        ->where('user_id', $r->user_id)
                        ->first();
                    $statusColor =
                        $r->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                @endphp

                <div class="rounded-lg border p-4 flex items-start justify-between">
                    <div>
                        <div class="font-semibold text-lg">{{ $survey->name }}</div>
                        <div class="text-sm text-gray-600">
                            {{ \Carbon\Carbon::parse($survey->start_date)->translatedFormat('d M Y') }}
                            –
                            {{ \Carbon\Carbon::parse($survey->end_date)->translatedFormat('d M Y') }}
                        </div>

                        <span class="inline-flex mt-2 px-2 py-0.5 rounded text-xs {{ $statusColor }}">
                            {{ ucfirst($r->status) }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        @if ($cert)
                            <x-filament::button tag="a" target="_blank"
                                href="{{ route('certificates.download', $cert) }}" color="success" size="sm">
                                Unduh Sertifikat
                            </x-filament::button>
                        @endif
                        @if (isAdmin())
                            <x-filament::button tag="a"
                                href="{{ url('/admin/surveys/' . $survey->id . '/edit') }}" size="sm">
                                Buka
                            </x-filament::button>
                        @endif

                    </div>
                </div>
            @empty
                <div class="text-gray-500">Tidak ada survei yang diikuti.</div>
            @endforelse

            <div>
                {{ $this->rows->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
