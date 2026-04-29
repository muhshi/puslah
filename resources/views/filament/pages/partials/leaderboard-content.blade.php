@php
    $top3 = $data->take(3);
    $rest = $data->skip(3);
@endphp

<!-- Container to force dark mode aesthetic for the leaderboard -->
<div class="bg-[#0b1120] rounded-[2rem] p-4 sm:p-8 shadow-2xl relative overflow-hidden font-sans border border-slate-800">
    <!-- Glow effects -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-2xl h-[300px] bg-blue-500/10 blur-[100px] rounded-full pointer-events-none"></div>

    <!-- Top 3 Podium Section -->
    <div class="flex flex-col md:flex-row justify-center items-end gap-4 md:gap-8 mb-10 pt-16 relative z-10">
        
        <!-- Rank 2 (Left) -->
        @if(isset($top3[1]))
            <div class="flex flex-col items-center w-full md:w-56 order-2 md:order-1 relative group">
                <div class="w-24 h-24 rounded-2xl overflow-hidden shadow-[0_10px_20px_rgba(0,0,0,0.5)] border border-slate-600 bg-[#1e293b] mb-4 relative z-20">
                    <img src="{{ asset('storage/' . $top3[1]->avatar) }}" class="w-full h-full object-cover">
                </div>
                <h3 class="font-bold text-white text-lg mb-6 text-center z-20 line-clamp-1 w-full px-2">{{ $top3[1]->name }}</h3>
                
                <div class="w-full bg-gradient-to-b from-[#1e293b] to-[#0b1120] rounded-t-xl border-t border-slate-600 flex flex-col items-center pt-6 pb-6 shadow-[inset_0_2px_10px_rgba(255,255,255,0.05)] z-10 min-h-[140px] relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-white/5 to-transparent rounded-t-xl pointer-events-none"></div>
                    <div class="bg-slate-700/50 p-2 rounded-lg text-slate-300 mb-2 relative z-20 border border-white/10">
                        <x-heroicon-s-trophy class="w-5 h-5" />
                    </div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider relative z-20">Pencapaian</p>
                    <p class="text-2xl font-bold text-white relative z-20 mt-1">{{ $top3[1]->count }} <span class="text-[10px] font-normal text-slate-500">Tugas</span></p>
                </div>
            </div>
        @endif

        <!-- Rank 1 (Center) -->
        @if(isset($top3[0]))
            <div class="flex flex-col items-center w-full md:w-64 order-1 md:order-2 relative z-30">
                <div class="relative w-32 h-32 rounded-2xl overflow-hidden shadow-[0_10px_30px_rgba(245,158,11,0.2)] border border-amber-500/50 bg-[#1e293b] mb-4 z-20">
                    <img src="{{ asset('storage/' . $top3[0]->avatar) }}" class="w-full h-full object-cover">
                </div>
                <div class="absolute -top-6 text-4xl animate-bounce drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] z-30">👑</div>
                
                <h3 class="font-bold text-white text-xl mb-6 text-center z-20 line-clamp-1 w-full px-2">{{ $top3[0]->name }}</h3>
                
                <div class="w-full bg-gradient-to-b from-[#1e293b] to-[#0b1120] rounded-t-xl border-t border-amber-500/50 flex flex-col items-center pt-8 pb-8 shadow-[inset_0_2px_20px_rgba(245,158,11,0.05)] z-10 min-h-[180px] relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-amber-500/5 to-transparent rounded-t-xl pointer-events-none"></div>
                    <div class="bg-amber-500/20 p-2.5 rounded-lg text-amber-400 mb-2 relative z-20 border border-amber-500/30">
                        <x-heroicon-s-trophy class="w-6 h-6" />
                    </div>
                    <p class="text-[10px] text-amber-500/70 uppercase tracking-wider relative z-20">Pencapaian</p>
                    <div class="flex items-center gap-2 mt-1 relative z-20">
                        <div class="text-amber-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                        </div>
                        <p class="text-4xl font-bold text-white">{{ $top3[0]->count }}</p>
                    </div>
                    <p class="text-[10px] text-slate-500 relative z-20 mt-1">Tugas Terselesaikan</p>
                </div>
            </div>
        @endif

        <!-- Rank 3 (Right) -->
        @if(isset($top3[2]))
            <div class="flex flex-col items-center w-full md:w-56 order-3 relative group md:mt-8">
                <div class="w-24 h-24 rounded-2xl overflow-hidden shadow-[0_10px_20px_rgba(0,0,0,0.5)] border border-slate-600 bg-[#1e293b] mb-4 relative z-20">
                    <img src="{{ asset('storage/' . $top3[2]->avatar) }}" class="w-full h-full object-cover">
                </div>
                <h3 class="font-bold text-white text-lg mb-6 text-center z-20 line-clamp-1 w-full px-2">{{ $top3[2]->name }}</h3>
                
                <div class="w-full bg-gradient-to-b from-[#1e293b] to-[#0b1120] rounded-t-xl border-t border-slate-600 flex flex-col items-center pt-6 pb-6 shadow-[inset_0_2px_10px_rgba(255,255,255,0.05)] z-10 min-h-[120px] relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-white/5 to-transparent rounded-t-xl pointer-events-none"></div>
                    <div class="bg-orange-500/20 p-2 rounded-lg text-orange-400 mb-2 relative z-20 border border-orange-500/20">
                        <x-heroicon-s-trophy class="w-5 h-5" />
                    </div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider relative z-20">Pencapaian</p>
                    <p class="text-2xl font-bold text-white relative z-20 mt-1">{{ $top3[2]->count }} <span class="text-[10px] font-normal text-slate-500">Tugas</span></p>
                </div>
            </div>
        @endif
    </div>

    <!-- List Section for Rank 4+ -->
    @if($rest->isNotEmpty())
    <div class="bg-[#1e293b] rounded-xl overflow-hidden shadow-lg border border-white/5 max-w-4xl mx-auto mt-4 relative z-20">
        <!-- List Header -->
        <div class="grid grid-cols-12 gap-4 p-4 text-[11px] font-medium text-slate-400 border-b border-white/5 bg-white/5">
            <div class="col-span-2 text-center">Rank</div>
            <div class="col-span-7">User name</div>
            <div class="col-span-3 text-right pr-6">Point</div>
        </div>
        
        <!-- List Items -->
        <div class="divide-y divide-white/5">
            @foreach($rest as $index => $item)
                <div class="grid grid-cols-12 gap-4 p-4 items-center hover:bg-white/5 transition-colors">
                    <div class="col-span-2 flex justify-center">
                        <span class="font-bold text-white text-base">{{ $index + 4 }}</span>
                    </div>
                    <div class="col-span-7 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl overflow-hidden bg-slate-800 shrink-0 border border-white/10">
                            <img src="{{ asset('storage/' . $item->avatar) }}" class="w-full h-full object-cover">
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-white text-sm truncate">{{ $item->name }}</p>
                            <p class="text-[10px] text-slate-400 truncate mt-0.5">{{ $item->jabatan }}</p>
                        </div>
                    </div>
                    <div class="col-span-3 flex items-center justify-end gap-2 pr-4">
                        <div class="text-blue-400">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                        </div>
                        <span class="text-base font-bold text-white">{{ $item->count }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Load More Button -->
    @if($limit < $total)
        <div class="mt-8 flex justify-center pb-4 relative z-20">
            <button wire:click="loadMore{{ $type }}" type="button" class="px-6 py-2.5 rounded-full bg-white/5 border border-white/10 text-xs font-bold text-slate-300 hover:bg-white/10 hover:text-white transition-all flex items-center gap-2">
                <span>Tampilkan {{ $total - $limit }} Lainnya</span>
                <x-heroicon-m-chevron-down class="w-4 h-4" />
            </button>
        </div>
    @endif
</div>
