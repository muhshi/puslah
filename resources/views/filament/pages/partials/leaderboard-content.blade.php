@php
    $top3 = $data->take(3);
    $rest = $data->skip(3);
@endphp

<!-- Container for the Leaderboard -->
<div class="max-w-5xl mx-auto py-10 font-sans">
    
    <!-- Top 3 Podium Section -->
    <div class="flex justify-center items-end gap-5 mb-16 mt-20 text-center relative z-10 px-4">
        
        <!-- Rank 2 (Left) -->
        @if(isset($top3[1]))
            <div class="w-40 sm:w-48 pt-0 pb-5 px-4 bg-gray-100 dark:bg-[#21262d] rounded-t-2xl rounded-b-lg order-1 h-[180px] shadow-sm relative flex flex-col justify-between">
                <div>
                    <div class="w-[70px] h-[70px] rounded-full mx-auto -mt-[35px] mb-2.5 border-4 border-gray-50 dark:border-gray-900 overflow-hidden bg-gray-300 dark:bg-gray-700">
                        <img src="{{ asset('storage/' . $top3[1]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm sm:text-base line-clamp-1 w-full">{{ $top3[1]->name }}</h3>
                    <p class="text-[11px] sm:text-xs text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[1]->count }} Tugas</p>
                </div>
                <div class="text-[#00d1ff] font-bold text-sm">
                    💎 #2
                </div>
            </div>
        @endif

        <!-- Rank 1 (Center) -->
        @if(isset($top3[0]))
            <div class="w-44 sm:w-52 pt-0 pb-6 px-4 bg-gray-100 dark:bg-[#21262d] rounded-t-2xl rounded-b-lg order-2 h-[220px] shadow-[0_10px_30px_rgba(0,123,255,0.15)] dark:shadow-[0_10px_30px_rgba(56,139,253,0.15)] border-2 border-[#007bff] dark:border-[#388bfd] relative flex flex-col justify-between z-20">
                <div class="absolute -top-[65px] left-1/2 -translate-x-1/2 text-2xl z-30">👑</div>
                <div>
                    <div class="w-[80px] h-[80px] rounded-full mx-auto -mt-[40px] mb-3 border-4 border-[#007bff] dark:border-[#388bfd] overflow-hidden bg-gray-300 dark:bg-gray-700 shadow-md">
                        <img src="{{ asset('storage/' . $top3[0]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-base sm:text-lg line-clamp-1 w-full">{{ $top3[0]->name }}</h3>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[0]->count }} Tugas</p>
                </div>
                <div class="text-[#00d1ff] font-bold text-base bg-[#00d1ff]/10 py-1.5 px-3 rounded-lg inline-block mx-auto mt-2">
                    💎 #1
                </div>
            </div>
        @endif

        <!-- Rank 3 (Right) -->
        @if(isset($top3[2]))
            <div class="w-40 sm:w-48 pt-0 pb-5 px-4 bg-gray-100 dark:bg-[#21262d] rounded-t-2xl rounded-b-lg order-3 h-[160px] shadow-sm relative flex flex-col justify-between">
                <div>
                    <div class="w-[70px] h-[70px] rounded-full mx-auto -mt-[35px] mb-2.5 border-4 border-gray-50 dark:border-gray-900 overflow-hidden bg-gray-300 dark:bg-gray-700">
                        <img src="{{ asset('storage/' . $top3[2]->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-[#f0f6fc] text-sm sm:text-base line-clamp-1 w-full">{{ $top3[2]->name }}</h3>
                    <p class="text-[11px] sm:text-xs text-gray-500 dark:text-[#8b949e] font-medium mt-1">{{ $top3[2]->count }} Tugas</p>
                </div>
                <div class="text-[#00d1ff] font-bold text-sm">
                    💎 #3
                </div>
            </div>
        @endif
    </div>

    <!-- List Section for Rank 4+ -->
    @if($rest->isNotEmpty())
    <div class="w-full bg-white dark:bg-[#161b22] rounded-xl overflow-hidden shadow-[0_4px_12px_rgba(0,0,0,0.05)] dark:shadow-[0_4px_12px_rgba(0,0,0,0.2)] mt-8">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-gray-200 dark:border-[#30363d]">
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-semibold w-16 text-center">Rank</th>
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-semibold">Nama Pegawai</th>
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-semibold text-center w-24">Tugas</th>
                    <th class="py-4 px-5 text-xs text-gray-500 dark:text-[#8b949e] uppercase font-semibold text-right w-24">Reward</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rest as $index => $item)
                    <tr class="border-b border-gray-100 dark:border-[#30363d]/50 hover:bg-gray-50 dark:hover:bg-[#21262d]/50 transition-colors">
                        <td class="py-3 px-5 text-center font-bold text-gray-700 dark:text-[#f0f6fc]">{{ $index + 4 }}</td>
                        <td class="py-3 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 border border-gray-200 dark:border-gray-600">
                                    <img src="{{ asset('storage/' . $item->avatar) }}" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-gray-900 dark:text-[#f0f6fc] truncate">{{ $item->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-[#8b949e] truncate">{{ $item->jabatan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-5 text-center font-bold text-gray-900 dark:text-[#f0f6fc]">
                            {{ $item->count }}
                        </td>
                        <td class="py-3 px-5 text-right font-bold text-[#00d1ff]">
                            💎
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
            <button wire:click="loadMore{{ $type }}" type="button" class="px-6 py-2.5 rounded-full bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] text-sm font-bold text-gray-700 dark:text-[#f0f6fc] hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors shadow-sm flex items-center gap-2">
                <span>Tampilkan {{ $total - $limit }} Lainnya</span>
                <x-heroicon-m-chevron-down class="w-4 h-4" />
            </button>
        </div>
    @endif
</div>

