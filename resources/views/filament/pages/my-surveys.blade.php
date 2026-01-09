<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        {{-- FILTER & SORT (Compact Single Line) --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-3 shadow-sm">
            <div class="flex flex-col md:flex-row gap-3">
                {{-- Search (Grow to fill) --}}
                <div class="flex-1 min-w-[200px]">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="h-9">
                        <x-filament::input wire:model.live.debounce.500ms="search"
                            placeholder="Cari survei..." class="text-sm" />
                    </x-filament::input.wrapper>
                </div>

                {{-- Filters Group (Auto width) --}}
                <div class="flex flex-wrap gap-3 shrink-0">
                    {{-- Status --}}
                    <div class="w-32">
                        <x-filament::input.wrapper class="h-9">
                            <x-filament::input.select wire:model.live="status" class="text-sm py-1">
                                <option value="">Semua Status</option>
                                <option value="registered">Terdaftar</option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    {{-- Year --}}
                    <div class="w-28">
                        <x-filament::input.wrapper class="h-9">
                            <x-filament::input.select wire:model.live="year" class="text-sm py-1">
                                <option value="">Semua Thn</option>
                                @foreach (range(now()->year, now()->year - 5) as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
                
                {{-- Sort Group (Auto width) --}}
                <div class="flex gap-2 shrink-0 border-l border-gray-200 dark:border-gray-700 pl-3">
                     <div class="w-32">
                        <x-filament::input.wrapper class="h-9">
                            <x-filament::input.select wire:model.live="sortBy" class="text-sm py-1">
                                <option value="surveys.start_date">Tgl Mulai</option>
                                <option value="surveys.end_date">Tgl Selesai</option>
                                <option value="surveys.name">Nama</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                     </div>
                     <div class="w-20">
                        <x-filament::input.wrapper class="h-9">
                            <x-filament::input.select wire:model.live="sortDir" class="text-sm py-1">
                                <option value="desc">Baru</option>
                                <option value="asc">Lama</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                     </div>
                </div>
            </div>
        </div>

        {{-- MAIN CONTENT (Grid of Cards) --}}
        <div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($this->rows as $r)
                    @php
                        $survey = $r->survey;
                        if(!$survey) continue;

                        $cert = \App\Models\Certificate::where('survey_id', $r->survey_id)
                            ->where('user_id', $r->user_id)
                            ->first();
                        
                        // Status Colors matching standard badges
                        $statusBadgeColor = match ($r->status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'registered' => 'warning',
                            default => 'gray',
                        };

                        $statusLabel = match ($r->status) {
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            'registered' => 'Terdaftar',
                            default => ucfirst($r->status),
                        };
                    @endphp

                    {{-- Card Design based on user feedback (Classic White) --}}
                    <div class="group flex flex-col bg-white dark:bg-gray-900 rounded-3xl p-6 transition-all hover:shadow-lg h-full relative overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-sky-400 dark:hover:border-sky-400">
                        {{-- Decorative Background Circle (Subtle Touch) --}}
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-sky-50 dark:bg-gray-800 rounded-full opacity-50 blur-2xl group-hover:scale-150 transition-transform duration-700"></div>

                        <div class="relative z-10 flex flex-col h-full">
                             {{-- Header: Title --}}
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 leading-tight">
                                {{ $survey->name }}
                            </h3>
                            
                            {{-- Description / Subtitle --}}
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                                {{ $survey->description ?? 'Tidak ada deskripsi survei.' }}
                            </p>

                            {{-- Tags / Badges Row --}}
                            <div class="flex flex-wrap gap-2 mb-6">
                                <x-filament::badge color="{{ $statusBadgeColor }}">
                                    {{ $statusLabel }}
                                </x-filament::badge>
                                
                                <x-filament::badge color="gray" icon="heroicon-m-calendar">
                                    {{ \Carbon\Carbon::parse($survey->start_date)->format('Y') }}
                                </x-filament::badge>

                                @if($r->score)
                                    <x-filament::badge color="info">
                                        Score: {{ $r->score }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            {{-- Footer: Actions (Push to bottom) --}}
                            <div class="mt-auto flex items-center justify-between pt-4 border-t border-gray-200/50 dark:border-gray-700/50">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{-- Optional: Left side footer text like 'Explore' or 'Action' --}}
                                    @if ($cert)
                                        <span class="text-green-600 flex items-center gap-1">
                                            <x-heroicon-m-check-badge class="w-4 h-4"/> Selesai
                                        </span>
                                    @else
                                        <span class="text-gray-500">Dalam Proses</span>
                                    @endif
                                </div>

                                <div class="flex gap-2">
                                     @if ($cert)
                                        <x-filament::icon-button 
                                            icon="heroicon-o-arrow-down-tray" 
                                            tag="a" 
                                            href="{{ route('certificates.download', $cert) }}" 
                                            color="success" 
                                            tooltip="Unduh Sertifikat" />
                                    @endif

                                    @if (isAdmin())
                                        <a href="{{ url('/admin/surveys/' . $survey->id . '/edit') }}" 
                                           class="p-2 bg-white dark:bg-gray-700 rounded-full shadow-sm hover:shadow-md transition-all text-gray-900 dark:text-white">
                                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center p-12 pb-16 text-center bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 border-dashed">
                        <div class="p-4 bg-sky-50 dark:bg-gray-800 rounded-full mb-4">
                            {{-- Using raw SVG to ensure rendering --}}
                            <!-- <x-heroicons::solid.user /> -->
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Belum ada survei</h3>
                        <p class="text-gray-500 max-w-sm mt-1">
                            Anda belum terdaftar dalam survei apapun saat ini.
                        </p>
                        <div class="p-4 bg-sky-50 dark:bg-gray-800 rounded-full mb-4">
                            {{-- Using raw SVG to ensure rendering --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-sky-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $this->rows->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>