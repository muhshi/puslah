@php
    $top3 = $data->take(3);
    $rest = $data->skip(3);
@endphp

<!-- Top 3 Podium Section -->
<div class="flex flex-col md:flex-row justify-center items-end gap-4 md:gap-6 mb-16 mt-8">
    
    <!-- Rank 2 (Left) -->
    @if(isset($top3[1]))
        <div class="flex flex-col items-center w-full md:w-64 transform md:translate-y-4 relative group order-2 md:order-1">
            <div class="absolute -top-4 -left-4 text-4xl opacity-20 z-0">2</div>
            <div class="relative w-24 h-24 mb-4 z-10">
                <img class="rounded-3xl w-full h-full object-cover shadow-[0_0_30px_rgba(148,163,184,0.3)] border-4 border-slate-200 dark:border-slate-700" src="{{ asset('storage/' . $top3[1]->avatar) }}" alt="{{ $top3[1]->name }}">
            </div>
            <h3 class="font-extrabold text-lg text-gray-900 dark:text-white line-clamp-1 text-center px-2 z-10">{{ $top3[1]->name }}</h3>
            
            <div class="mt-5 w-full bg-gradient-to-b from-slate-50 to-white dark:from-slate-800 dark:to-slate-900 rounded-t-3xl border-t-[6px] border-slate-300 dark:border-slate-600 p-6 flex flex-col items-center shadow-xl">
                <div class="bg-slate-100 dark:bg-slate-700 p-2.5 rounded-xl text-slate-500 dark:text-slate-300 mb-2">
                    <x-heroicon-s-trophy class="w-6 h-6" />
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pencapaian</p>
                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ $top3[1]->count }} <span class="text-[10px] text-gray-400">Tugas</span></p>
            </div>
        </div>
    @endif

    <!-- Rank 1 (Center) -->
    @if(isset($top3[0]))
        <div class="flex flex-col items-center w-full md:w-72 z-20 order-1 md:order-2">
            <div class="relative w-32 h-32 mb-4">
                <img class="rounded-3xl w-full h-full object-cover shadow-[0_0_40px_rgba(245,158,11,0.4)] border-4 border-amber-200 dark:border-amber-600/50" src="{{ asset('storage/' . $top3[0]->avatar) }}" alt="{{ $top3[0]->name }}">
                <div class="absolute -top-6 -right-4 text-5xl animate-bounce drop-shadow-xl filter">👑</div>
            </div>
            <h3 class="font-black text-xl text-gray-900 dark:text-white line-clamp-1 text-center px-2">{{ $top3[0]->name }}</h3>
            
            <div class="mt-5 w-full bg-gradient-to-b from-amber-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-t-3xl border-t-[8px] border-amber-400 dark:border-amber-500 p-8 flex flex-col items-center shadow-2xl relative overflow-hidden">
                <div class="absolute inset-0 bg-amber-400 opacity-5 dark:opacity-10" style="background-image: radial-gradient(circle at 2px 2px, currentColor 1px, transparent 0); background-size: 16px 16px;"></div>
                <div class="relative z-10 bg-amber-100 dark:bg-amber-500/20 p-3 rounded-2xl text-amber-500 dark:text-amber-400 mb-2 shadow-inner ring-1 ring-amber-200 dark:ring-amber-500/30">
                    <x-heroicon-s-trophy class="w-8 h-8" />
                </div>
                <p class="relative z-10 text-xs font-black text-amber-600/70 dark:text-amber-500/70 uppercase tracking-widest">Pencapaian</p>
                <p class="relative z-10 text-4xl font-black text-gray-900 dark:text-white mt-1">{{ $top3[0]->count }} <span class="text-xs text-gray-400">Tugas</span></p>
            </div>
        </div>
    @endif

    <!-- Rank 3 (Right) -->
    @if(isset($top3[2]))
        <div class="flex flex-col items-center w-full md:w-64 transform md:translate-y-8 relative group order-3">
             <div class="absolute -top-4 -right-4 text-4xl opacity-20 z-0">3</div>
            <div class="relative w-24 h-24 mb-4 z-10">
                <img class="rounded-3xl w-full h-full object-cover shadow-[0_0_30px_rgba(249,115,22,0.3)] border-4 border-orange-200 dark:border-orange-900/50" src="{{ asset('storage/' . $top3[2]->avatar) }}" alt="{{ $top3[2]->name }}">
            </div>
            <h3 class="font-extrabold text-lg text-gray-900 dark:text-white line-clamp-1 text-center px-2 z-10">{{ $top3[2]->name }}</h3>
            
            <div class="mt-5 w-full bg-gradient-to-b from-orange-50 to-white dark:from-slate-800 dark:to-slate-900 rounded-t-3xl border-t-[6px] border-orange-300 dark:border-orange-600/70 p-6 flex flex-col items-center shadow-xl">
                <div class="bg-orange-100 dark:bg-orange-500/20 p-2.5 rounded-xl text-orange-500 dark:text-orange-400 mb-2">
                    <x-heroicon-s-trophy class="w-6 h-6" />
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pencapaian</p>
                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1">{{ $top3[2]->count }} <span class="text-[10px] text-gray-400">Tugas</span></p>
            </div>
        </div>
    @endif
</div>

<!-- List Section for Rank 4+ -->
@if($rest->isNotEmpty())
<div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden max-w-4xl mx-auto">
    <!-- List Header -->
    <div class="grid grid-cols-12 gap-4 p-5 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50 text-[10px] font-black text-gray-400 uppercase tracking-widest">
        <div class="col-span-2 sm:col-span-1 text-center">Rank</div>
        <div class="col-span-7 sm:col-span-8 px-2">Identitas Pegawai</div>
        <div class="col-span-3 text-right pr-4">Pencapaian</div>
    </div>
    
    <!-- List Items -->
    <div class="divide-y divide-gray-50 dark:divide-gray-800">
        @foreach($rest as $index => $item)
            <div class="grid grid-cols-12 gap-4 p-4 sm:p-5 items-center hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group">
                <div class="col-span-2 sm:col-span-1 flex justify-center">
                    <span class="font-black text-gray-400 dark:text-gray-500 text-xl">{{ $index + 4 }}</span>
                </div>
                <div class="col-span-7 sm:col-span-8 flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl overflow-hidden border-2 border-gray-100 dark:border-gray-700 flex-shrink-0 shadow-sm group-hover:shadow-md transition-shadow">
                        <img src="{{ asset('storage/' . $item->avatar) }}" class="w-full h-full object-cover">
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-gray-900 dark:text-white truncate text-base">{{ $item->name }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 truncate mt-0.5">{{ $item->jabatan }}</p>
                    </div>
                </div>
                <div class="col-span-3 flex items-center justify-end gap-1.5 pr-4">
                    <span class="text-2xl font-black text-primary-600 dark:text-primary-400">{{ $item->count }}</span>
                    <span class="text-[10px] font-bold text-gray-400 uppercase hidden sm:inline">Tugas</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Load More Button -->
@if($limit < $total)
    <div class="mt-10 flex justify-center pb-10">
        <button wire:click="loadMore{{ $type }}" type="button" class="px-8 py-3 rounded-full bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 shadow-sm text-sm font-bold text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:border-primary-500 dark:hover:text-primary-400 transition-all flex items-center gap-2 group">
            <span>Tampilkan {{ $total - $limit }} Lainnya</span>
            <x-heroicon-m-chevron-down class="w-5 h-5 group-hover:translate-y-1 transition-transform" />
        </button>
    </div>
@endif
