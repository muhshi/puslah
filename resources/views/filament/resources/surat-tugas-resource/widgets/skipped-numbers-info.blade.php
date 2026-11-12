<x-filament::widget>
    <x-filament::section>
        @php
            $stSkippedCount = $this->getSkippedNumbersCount();
            $sppdSkippedCount = $this->getSppdSkippedNumbersCount();
            $missingByMonth = $activeTab === 'sppd'
                ? $this->getSppdSkippedNumbersByMonth()
                : $this->getSkippedNumbersByMonth();
            $blockedGroups = $this->getBlockedGroupsDetails();
        @endphp

        {{-- Header: Title, Tab Switcher, Year Selector --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
                    Monitor Nomor Surat
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Daftar nomor urut yang belum digunakan atau terlewat.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Segmented Tab Switcher --}}
                <div class="inline-flex items-center rounded-xl bg-gray-100 dark:bg-gray-800/80 p-1 border border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        wire:click="setActiveTab('surat_tugas')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $activeTab === 'surat_tugas' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}"
                    >
                        <span>Surat Tugas</span>
                        @if ($stSkippedCount > 0)
                            <span class="inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-900/60 px-2 py-0.5 text-[11px] font-bold text-warning-700 dark:text-warning-300 whitespace-nowrap">
                                {{ $stSkippedCount }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-success-100 dark:bg-success-900/60 px-1.5 py-0.5 text-[11px] font-bold text-success-700 dark:text-success-300 whitespace-nowrap">
                                ✓
                            </span>
                        @endif
                    </button>

                    <button
                        type="button"
                        wire:click="setActiveTab('sppd')"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $activeTab === 'sppd' ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200' }}"
                    >
                        <span>SPPD</span>
                        @if ($sppdSkippedCount > 0)
                            <span class="inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-900/60 px-2 py-0.5 text-[11px] font-bold text-warning-700 dark:text-warning-300 whitespace-nowrap">
                                {{ $sppdSkippedCount }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-success-100 dark:bg-success-900/60 px-1.5 py-0.5 text-[11px] font-bold text-success-700 dark:text-success-300 whitespace-nowrap">
                                ✓
                            </span>
                        @endif
                    </button>
                </div>

                {{-- Year Selector --}}
                <div class="w-28">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedYear">
                            @foreach ($this->getAvailableYears() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </div>

        <div class="mt-5 space-y-5">
            {{-- Bagian Nomor Terlewat --}}
            <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2 w-2 rounded-full {{ !empty($missingByMonth) ? 'bg-warning-500' : 'bg-success-500' }}"></span>
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            Belum Terpakai / Terlewat {{ $activeTab === 'sppd' ? '(SPPD)' : '(Surat Tugas)' }}
                        </h3>
                    </div>
                    @php
                        $activeSkippedCount = $activeTab === 'sppd' ? $sppdSkippedCount : $stSkippedCount;
                    @endphp
                    @if ($activeSkippedCount > 0)
                        <span class="text-xs font-medium text-warning-700 dark:text-warning-300 bg-warning-50 dark:bg-warning-950/60 border border-warning-200 dark:border-warning-800/60 rounded-full px-2.5 py-0.5 whitespace-nowrap">
                            Total {{ $activeSkippedCount }} nomor terlewat
                        </span>
                    @endif
                </div>

                @if (!empty($missingByMonth))
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($missingByMonth as $month => $ranges)
                            @php
                                $tokens = array_filter(array_map('trim', explode(',', $ranges)));
                            @endphp
                            <div class="rounded-xl bg-warning-50/70 dark:bg-warning-950/30 border border-warning-200/80 dark:border-warning-800/50 p-3.5 shadow-sm flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-2 border-b border-warning-200/60 dark:border-warning-800/40 pb-1.5">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-danger-600 dark:text-danger-400">
                                        {{ $month }}
                                    </h4>
                                    <span class="text-[11px] font-medium text-warning-700/80 dark:text-warning-300/80 whitespace-nowrap">
                                        {{ count($tokens) }} segmen
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($tokens as $token)
                                        <span class="inline-flex items-center rounded-md bg-white/90 dark:bg-gray-900/80 px-2 py-0.5 text-xs font-mono font-semibold text-warning-800 dark:text-warning-300 border border-warning-200/80 dark:border-warning-800/60 shadow-2xs">
                                            {{ $token }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-xl bg-success-50/80 dark:bg-success-950/40 border border-success-200 dark:border-success-800/50 p-4">
                        <div class="flex items-center gap-3">
                            <x-heroicon-m-check-circle class="h-5 w-5 text-success-500 shrink-0" />
                            <p class="text-sm font-medium text-success-800 dark:text-success-200">
                                Tidak ada nomor {{ $activeTab === 'sppd' ? 'SPPD' : 'Surat Tugas' }} yang terlewat di tahun {{ $selectedYear }}.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Bagian Nomor Di-block (khusus Surat Tugas) --}}
            @if ($activeTab === 'surat_tugas' && !empty($blockedGroups))
                <div class="pt-1">
                    <div class="rounded-xl bg-gray-50/80 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 p-4 space-y-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center rounded-lg bg-danger-50 dark:bg-danger-950/60 p-1.5 text-danger-600 dark:text-danger-400 border border-danger-200/70 dark:border-danger-800/50">
                                    <x-heroicon-m-lock-closed class="h-4 w-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Nomor Di-block (Khusus / Reserved)
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Tahun {{ $selectedYear }} &bull; {{ count($blockedGroups) }} grup terblokir
                                    </p>
                                </div>
                            </div>

                            <x-filament::button
                                href="{{ App\Filament\Resources\SuratTugasResource::getUrl('manage-blocked-numbers') }}"
                                tag="a"
                                color="gray"
                                size="sm"
                                icon="heroicon-m-cog-6-tooth"
                            >
                                Kelola Semua
                            </x-filament::button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                            @foreach ($blockedGroups as $group)
                                <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-800/90 p-4 shadow-sm border border-gray-200/80 dark:border-gray-700/80 gap-3.5">
                                    <div class="space-y-2.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <h5 class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-100 line-clamp-2 leading-snug" title="{{ $group['keterangan'] }}">
                                                {{ $group['keterangan'] }}
                                            </h5>
                                            <span class="inline-flex items-center rounded-full bg-danger-50 dark:bg-danger-950/60 px-2.5 py-0.5 text-[11px] font-semibold text-danger-700 dark:text-danger-300 border border-danger-200 dark:border-danger-800/70 whitespace-nowrap shrink-0">
                                                {{ $group['count'] }} nomor
                                            </span>
                                        </div>

                                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/70 border border-gray-200/60 dark:border-gray-700/60 px-3 py-2">
                                            <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-0.5">
                                                Rentang Nomor
                                            </div>
                                            <div class="text-sm font-mono font-bold text-primary-600 dark:text-primary-400 break-words">
                                                {{ $group['ranges'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 pt-2.5 border-t border-gray-100 dark:border-gray-700/60">
                                        <x-filament::button
                                            wire:click="mountAction('buatSuratTugas', { keterangan: '{{ addslashes($group['keterangan']) }}' })"
                                            color="success"
                                            size="xs"
                                            icon="heroicon-m-document-plus"
                                            class="flex-1"
                                        >
                                            Buat ST
                                        </x-filament::button>

                                        <x-filament::button
                                            wire:click="mountAction('releaseBlockedGroup', { keterangan: '{{ addslashes($group['keterangan']) }}' })"
                                            color="warning"
                                            size="xs"
                                            icon="heroicon-m-lock-open"
                                            outlined
                                        >
                                            Release
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament::widget>
