@php
    $top3 = $data->take(3);
    $rest = $data->skip(3);
@endphp

<!-- Container for the Leaderboard -->
<div class="max-w-5xl mx-auto py-10 font-sans">
    
    <!-- Top 3 Podium Section -->
    <div class="flex justify-center items-end gap-3 sm:gap-6 md:gap-8 mb-16 mt-24 text-center relative z-10 px-2 sm:px-4">
        
        <!-- Rank 2 (Left) -->
        @if(isset($top3[1]))
            <div class="w-40 sm:w-48 pt-0 pb-5 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-1 h-[170px] shadow-lg border border-gray-100 dark:border-gray-800 relative flex flex-col justify-between">
                <div>
                    <div style="width: 80px; height: 80px; margin-top: -40px;" class="rounded-full mx-auto mb-3 border-4 border-white dark:border-[#21262d] overflow-hidden bg-gray-200 shadow-md">
                        <img src="{{ asset('storage/' . $top3[1]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm sm:text-base line-clamp-1 w-full">{{ $top3[1]->name }}</h3>
                    <p class="text-xs text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[1]->count }} Tugas</p>
                </div>
                <div class="text-gray-400 dark:text-gray-500 font-black text-lg">
                    #2
                </div>
            </div>
        @endif

        <!-- Rank 1 (Center) -->
        @if(isset($top3[0]))
            <div class="w-44 sm:w-52 pt-0 pb-6 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-2 h-[210px] shadow-xl border-2 border-primary-500 dark:border-primary-500 relative flex flex-col justify-between z-20 ring-4 ring-primary-50 dark:ring-primary-900/20">
                <div class="absolute -top-16 left-1/2 -translate-x-1/2 text-4xl z-30 drop-shadow-md">👑</div>
                <div>
                    <div style="width: 96px; height: 96px; margin-top: -48px;" class="rounded-full mx-auto mb-3 border-4 border-primary-500 overflow-hidden bg-gray-200 shadow-lg">
                        <img src="{{ asset('storage/' . $top3[0]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-base sm:text-lg line-clamp-1 w-full">{{ $top3[0]->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[0]->count }} Tugas</p>
                </div>
                <div class="text-primary-600 dark:text-primary-400 font-black text-xl">
                    #1
                </div>
            </div>
        @endif

        <!-- Rank 3 (Right) -->
        @if(isset($top3[2]))
            <div class="w-40 sm:w-48 pt-0 pb-5 px-4 bg-white dark:bg-[#21262d] rounded-t-3xl rounded-b-xl order-3 h-[150px] shadow-lg border border-gray-100 dark:border-gray-800 relative flex flex-col justify-between">
                <div>
                    <div style="width: 80px; height: 80px; margin-top: -40px;" class="rounded-full mx-auto mb-3 border-4 border-white dark:border-[#21262d] overflow-hidden bg-gray-200 shadow-md">
                        <img src="{{ asset('storage/' . $top3[2]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm sm:text-base line-clamp-1 w-full">{{ $top3[2]->name }}</h3>
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
    <div class="w-full bg-white dark:bg-[#161b22] rounded-2xl overflow-hidden shadow-lg border border-gray-100 dark:border-gray-800 mt-8">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50/50 dark:bg-gray-800/30">
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold w-16 text-center">Rank</th>
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold">Nama Pegawai</th>
                    <th class="py-4 px-6 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-bold text-right w-32">Pencapaian</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rest as $index => $item)
                    <tr class="border-b border-gray-100 dark:border-[#30363d]/50 hover:bg-gray-50 dark:hover:bg-[#21262d]/50 transition-colors">
                        <td class="py-3 px-5 text-center font-black text-gray-400 dark:text-gray-500 text-lg">{{ $index + 4 }}</td>
                        <td class="py-3 px-5">
                            <div class="flex items-center gap-4">
                                <div style="width: 44px; height: 44px; min-width: 44px;" class="rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 border border-gray-200 dark:border-gray-600 shadow-sm">
                                    <img src="{{ asset('storage/' . $item->avatar) }}" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-sm sm:text-base text-gray-900 dark:text-[#f0f6fc] truncate">{{ $item->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-[#8b949e] truncate mt-0.5">{{ $item->jabatan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-6 text-right">
                            <span class="font-black text-lg text-primary-600 dark:text-primary-400">{{ $item->count }}</span>
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
            <button wire:click="loadMore{{ $type }}" type="button" class="px-6 py-2.5 rounded-full bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] text-sm font-bold text-gray-700 dark:text-[#f0f6fc] hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors shadow-md flex items-center gap-2">
                <span>Tampilkan {{ $total - $limit }} Lainnya</span>
                <x-heroicon-m-chevron-down class="w-4 h-4" />
            </button>
        </div>
    @endif
</div>


