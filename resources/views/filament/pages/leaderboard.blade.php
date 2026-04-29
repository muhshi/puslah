<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Filter Section -->
        <x-filament::section class="max-w-xl mx-auto shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-primary-100 rounded-lg dark:bg-primary-900/30 text-primary-600">
                    <x-heroicon-o-funnel class="w-5 h-5" />
                </div>
                <h3 class="font-bold text-gray-900 dark:text-white">Filter Periode</h3>
            </div>
            <form wire:submit.prevent="updateData">
                {{ $this->form }}
            </form>
        </x-filament::section>

        <!-- Main Header -->
        <div class="text-center py-4 relative">
            <h2 class="text-3xl font-black tracking-tight text-gray-950 dark:text-white md:text-4xl flex items-center justify-center gap-3">
                🏆 Leaderboard Tugas
            </h2>
            <p class="text-lg font-medium text-gray-500 dark:text-gray-400 mt-2">
                Periode: <span class="text-primary-600 font-bold">{{ $currentMonthName }}</span>
            </p>
        </div>

        <div x-data="{ activeTab: 'pegawai' }" class="space-y-8">
            <!-- Tabs Navigation -->
            <div class="flex justify-center">
                <div class="flex p-1 space-x-1 bg-gray-200/50 rounded-xl dark:bg-gray-800/50 w-full max-w-sm">
                    <button 
                        @click="activeTab = 'pegawai'"
                        :class="activeTab === 'pegawai' ? 'bg-white text-primary-600 shadow-sm dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex-1 px-4 py-2.5 text-sm font-bold transition-all duration-200 rounded-lg"
                    >
                        Pegawai
                    </button>
                    <button 
                        @click="activeTab = 'mitra'"
                        :class="activeTab === 'mitra' ? 'bg-white text-primary-600 shadow-sm dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex-1 px-4 py-2.5 text-sm font-bold transition-all duration-200 rounded-lg"
                    >
                        Mitra
                    </button>
                </div>
            </div>

            <!-- Pegawai Content -->
            <div x-show="activeTab === 'pegawai'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                @if($pegawaiData->isEmpty())
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400 italic bg-white dark:bg-gray-800/50 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                        Belum ada data tugas untuk periode ini.
                    </div>
                @else
                    @include('filament.pages.partials.leaderboard-content', ['data' => $pegawaiData, 'limit' => $pegawaiLimit, 'total' => $totalPegawai, 'type' => 'Pegawai'])
                @endif
            </div>

            <!-- Mitra Content -->
            <div x-show="activeTab === 'mitra'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                @if($mitraData->isEmpty())
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400 italic bg-white dark:bg-gray-800/50 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                        Belum ada data tugas untuk periode ini.
                    </div>
                @else
                    @include('filament.pages.partials.leaderboard-content', ['data' => $mitraData, 'limit' => $mitraLimit, 'total' => $totalMitra, 'type' => 'Mitra'])
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
