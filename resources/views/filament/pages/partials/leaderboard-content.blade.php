@php
    $top3 = $data->take(3);
    $rest = $data->skip(3);
@endphp

<!-- Container for the Leaderboard -->
<div class="max-w-5xl mx-auto py-10 font-sans">

    <!-- Top 3 Podium Section -->
    <div style="margin-top: 70px; margin-bottom: 30px; gap: 20px;"
        class="flex justify-center items-end text-center relative z-10 px-2 sm:px-4 flex-wrap sm:flex-nowrap">

        <!-- Rank 2 (Left) -->
        @if(isset($top3[1]))
            <div style="width: 170px; height: 180px;"
                class="pt-0 pb-5 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-2 sm:order-1 shadow-lg border border-gray-100 dark:border-gray-800 relative flex flex-col justify-between">
                <div>
                    <div style="width: 80px; height: 80px; margin-top: -40px; min-height: 80px;"
                        class="rounded-full mx-auto mb-3 border-4 border-white dark:border-[#21262d] overflow-hidden bg-gray-200 shadow-md">
                        <img src="{{ asset('storage/' . $top3[1]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;"
                        class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm">{{ $top3[1]->name }}</h3>
                    <p class="text-xs text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[1]->count }} Tugas</p>
                </div>
                <div class="text-gray-400 dark:text-gray-500 font-black text-lg">
                    #2
                </div>
            </div>
        @endif

        <!-- Rank 1 (Center) -->
        @if(isset($top3[0]))
            <div style="width: 200px; height: 220px;"
                class="pt-0 pb-6 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-1 sm:order-2 shadow-xl border-2 border-primary-500 dark:border-primary-500 relative flex flex-col justify-between z-20 ring-4 ring-primary-50 dark:ring-primary-900/20">
                <div class="absolute -top-16 left-1/2 -translate-x-1/2 text-4xl z-30 drop-shadow-md">👑</div>
                <div>
                    <div style="width: 100px; height: 100px; margin-top: -50px; min-height: 100px;"
                        class="rounded-full mx-auto mb-3 border-4 border-primary-500 overflow-hidden bg-gray-200 shadow-lg">
                        <img src="{{ asset('storage/' . $top3[0]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;"
                        class="font-bold text-gray-900 dark:text-[#f0f6fc] text-base">{{ $top3[0]->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[0]->count }} Tugas</p>
                </div>
                <div class="text-primary-600 dark:text-primary-400 font-black text-xl">
                    #1
                </div>
            </div>
        @endif

        <!-- Rank 3 (Right) -->
        @if(isset($top3[2]))
            <div style="width: 170px; height: 160px;"
                class="pt-0 pb-5 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-3 shadow-lg border border-gray-100 dark:border-gray-800 relative flex flex-col justify-between">
                <div>
                    <div style="width: 80px; height: 80px; margin-top: -40px; min-height: 80px;"
                        class="rounded-full mx-auto mb-3 border-4 border-white dark:border-[#21262d] overflow-hidden bg-gray-200 shadow-md">
                        <img src="{{ asset('storage/' . $top3[2]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;"
                        class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm">{{ $top3[2]->name }}</h3>
                    <p class="text-xs text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[2]->count }} Tugas</p>
                </div>
                <div class="text-gray-400 dark:text-gray-500 font-black text-lg">
                    #3
                </div>
            </div>
        @endif
    </div>

    <!-- List Section for Rank 4+ -->
    @if($rest->isNotEmpty())
        <div
            class="w-full bg-white dark:bg-[#161b22] rounded-2xl overflow-hidden shadow-lg border border-gray-100 dark:border-gray-800 mt-8">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50/50 dark:bg-gray-800/30">
                        <th
                            class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold w-16 text-center">
                            Rank</th>
                        <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold">Nama Pegawai
                        </th>
                        <th class="py-4 px-6 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold text-right w-32">
                            Pencapaian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rest as $index => $item)
                        <tr
                            class="border-b border-gray-100 dark:border-[#30363d]/50 hover:bg-gray-50 dark:hover:bg-[#21262d]/50 transition-colors">
                            <td class="py-3 px-5 text-center font-black text-gray-400 dark:text-gray-500 text-lg">
                                {{ $index + 4 }}</td>
                            <td class="py-3 px-5">
                                <div class="flex items-center gap-4">
                                    <div style="width: 44px; height: 44px; min-width: 44px;"
                                        class="rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 border border-gray-200 dark:border-gray-600 shadow-sm">
                                        <img src="{{ asset('storage/' . $item->avatar) }}" class="w-full h-full object-cover">
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-sm sm:text-base text-gray-900 dark:text-[#f0f6fc] truncate">
                                            {{ $item->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-[#8b949e] truncate mt-0.5">
                                            {{ $item->jabatan }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-6 text-right">
                                <span
                                    class="font-black text-lg text-primary-600 dark:text-primary-400">{{ $item->count }}</span>
                                <span class="text-xs text-gray-400 font-bold ml-1">Tugas</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Load More Button -->
    @if($limit < $total)
        <div class="mt-8 flex justify-center pb-4">
            <button wire:click="loadMore{{ $type }}" wire:loading.attr="disabled" type="button" class="px-6 py-2.5 rounded-full bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] text-sm font-bold text-gray-700 dark:text-[#f0f6fc] hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors shadow-md flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="loadMore{{ $type }}">Tampilkan {{ $total - $limit }} Lainnya</span>
                <x-heroicon-m-chevron-down wire:loading.remove wire:target="loadMore{{ $type }}" class="w-4 h-4" />
                
                <span wire:loading wire:target="loadMore{{ $type }}">Memuat data...</span>
                <svg wire:loading wire:target="loadMore{{ $type }}" class="animate-spin -ml-1 mr-3 h-4 w-4 text-gray-700 dark:text-[#f0f6fc]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </div>
    @endif
</div>