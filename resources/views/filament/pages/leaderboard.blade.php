<x-filament-panels::page>
    <div class="space-y-10">
        <!-- Header Section with Premium Look -->
        <div class="relative overflow-hidden rounded-3xl bg-primary-600 p-8 text-white shadow-2xl dark:bg-primary-500">
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-center gap-6">
                    <div class="inline-flex items-center justify-center rounded-2xl bg-white/20 p-4 backdrop-blur-md shadow-inner">
                        <x-heroicon-o-trophy class="h-10 w-10 text-white" />
                    </div>
                    <div>
                        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight">Leaderboard Performa</h2>
                        <p class="mt-2 text-primary-100 text-lg font-medium opacity-90">
                             Apresiasi untuk dedikasi tim terbaik di bulan <span class="text-white font-bold">{{ $currentMonthName }}</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Decorative Elements -->
            <div class="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-16 -left-16 h-80 w-80 rounded-full bg-primary-400/20 blur-3xl"></div>
            <div class="absolute right-1/4 top-0 h-32 w-32 rounded-full bg-white/5 blur-2xl"></div>
        </div>

        <div x-data="{ activeTab: 'pegawai' }" class="space-y-8">
            <!-- Navigation Tabs -->
            <div class="flex justify-center">
                <nav class="flex p-1 space-x-1 bg-gray-100 rounded-2xl dark:bg-gray-800/50 w-full max-w-md shadow-inner">
                    <button 
                        @click="activeTab = 'pegawai'"
                        :class="activeTab === 'pegawai' ? 'bg-white text-primary-600 shadow-md dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex-1 px-4 py-3 text-sm font-bold transition-all duration-300 rounded-xl outline-none focus:outline-none"
                    >
                        Pegawai (Organik)
                    </button>
                    <button 
                        @click="activeTab = 'mitra'"
                        :class="activeTab === 'mitra' ? 'bg-white text-primary-600 shadow-md dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex-1 px-4 py-3 text-sm font-bold transition-all duration-300 rounded-xl outline-none focus:outline-none"
                    >
                        Mitra
                    </button>
                </nav>
            </div>

            <!-- Leaderboard Content -->
            @php
                $maxCount = max($pegawaiData->max('count'), $mitraData->max('count'), 1);
            @endphp

            <!-- Pegawai Tab -->
            <div x-show="activeTab === 'pegawai'" 
                 x-transition:enter="transition ease-out duration-500" 
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4" 
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                
                @forelse($pegawaiData as $index => $item)
                    @include('filament.pages.leaderboard-card', ['item' => $item, 'index' => $index, 'maxCount' => $maxCount])
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                        <div class="rounded-full bg-gray-100 p-6 dark:bg-gray-800 mb-4">
                            <x-heroicon-o-clipboard-document-list class="h-12 w-12 opacity-20" />
                        </div>
                        <p class="text-xl font-medium italic">Belum ada data tugas bulan ini.</p>
                    </div>
                @endforelse
            </div>

            <!-- Mitra Tab -->
            <div x-show="activeTab === 'mitra'" 
                 x-transition:enter="transition ease-out duration-500" 
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4" 
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 style="display: none;"
                 class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                
                @forelse($mitraData as $index => $item)
                    @include('filament.pages.leaderboard-card', ['item' => $item, 'index' => $index, 'maxCount' => $maxCount])
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-500 dark:text-gray-400">
                        <div class="rounded-full bg-gray-100 p-6 dark:bg-gray-800 mb-4">
                            <x-heroicon-o-clipboard-document-list class="h-12 w-12 opacity-20" />
                        </div>
                        <p class="text-xl font-medium italic">Belum ada data tugas bulan ini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <style>
        @keyframes shine {
            to {
                background-position: 200% center;
            }
        }
        .animate-shine {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            background-size: 200% 100%;
            animation: shine 2s infinite linear;
        }
    </style>
</x-filament-panels::page>
