<div class="group relative flex flex-col h-full">
    <!-- Rank Floating Badge -->
    <div @class([
        'absolute -left-4 -top-4 z-20 flex h-12 w-12 items-center justify-center rounded-2xl font-black text-lg shadow-xl transition-transform duration-300 group-hover:scale-110 group-hover:rotate-12',
        'bg-gradient-to-br from-amber-300 via-amber-400 to-yellow-600 text-amber-950 ring-4 ring-amber-400/30 shadow-amber-500/20' => $index == 0,
        'bg-gradient-to-br from-slate-200 via-slate-300 to-slate-500 text-slate-800 ring-4 ring-slate-300/30 shadow-slate-500/20' => $index == 1,
        'bg-gradient-to-br from-orange-300 via-orange-400 to-orange-600 text-orange-950 ring-4 ring-orange-400/30 shadow-orange-500/20' => $index == 2,
        'bg-white text-gray-500 ring-4 ring-gray-100 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700/50' => $index > 2,
    ])>
        {{ $index + 1 }}
    </div>

    <!-- Main Card Body -->
    <div class="flex-1 overflow-hidden rounded-[2.5rem] border border-gray-100 bg-white shadow-sm transition-all duration-500 group-hover:border-primary-500/50 group-hover:shadow-2xl group-hover:shadow-primary-500/10 dark:border-gray-800 dark:bg-gray-900 flex flex-col relative">
        
        <!-- Subtle Pattern Background for Top 3 -->
        @if($index < 3)
            <div @class([
                'absolute inset-0 opacity-[0.03] transition-opacity group-hover:opacity-[0.06]',
                'bg-amber-500' => $index == 0,
                'bg-slate-500' => $index == 1,
                'bg-orange-500' => $index == 2,
            ]) style="background-image: radial-gradient(circle at 2px 2px, currentColor 1px, transparent 0); background-size: 24px 24px;"></div>
        @endif

        <div class="relative p-8 flex flex-col items-center text-center flex-1 z-10">
            <!-- Profile Image / Initials -->
            <div class="relative mb-6">
                <div @class([
                    'h-28 w-28 overflow-hidden rounded-[2.2rem] shadow-2xl transition-all duration-500 group-hover:scale-105 group-hover:shadow-primary-500/20',
                    'ring-8 ring-amber-400/10' => $index == 0,
                    'ring-8 ring-slate-400/10' => $index == 1,
                    'ring-8 ring-orange-400/10' => $index == 2,
                    'ring-8 ring-gray-50 dark:ring-gray-800/50' => $index > 2,
                ])>
                    @if($item->avatar)
                        <img src="{{ asset('storage/' . $item->avatar) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-50 to-primary-100 text-2xl font-black text-primary-600 dark:from-gray-800 dark:to-gray-700 dark:text-primary-400">
                            {{ $item->initials }}
                        </div>
                    @endif
                </div>

                <!-- Crown for Rank 1 -->
                @if($index == 0)
                    <div class="absolute -right-3 -top-3 h-10 w-10 rotate-12 drop-shadow-lg animate-bounce">
                        <span class="text-3xl">👑</span>
                    </div>
                @endif
            </div>

            <!-- Identity -->
            <div class="space-y-1">
                <h3 class="line-clamp-1 text-xl font-black text-gray-900 dark:text-white transition-colors duration-300 group-hover:text-primary-600 dark:group-hover:text-primary-400">
                    {{ $item->name }}
                </h3>
                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em]">
                    {{ $item->jabatan }}
                </p>
            </div>

            <!-- Task Counter -->
            <div class="mt-8 w-full space-y-4">
                <div class="flex items-end justify-between px-1">
                    <span class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-wider">Pencapaian</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl font-black text-gray-900 dark:text-white leading-none">{{ $item->count }}</span>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">Tugas</span>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="relative h-3 w-full rounded-full bg-gray-100 dark:bg-gray-800/50 p-0.5 shadow-inner overflow-hidden">
                    <div 
                        class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-600 shadow-lg shadow-primary-500/30 transition-all duration-1000 ease-out relative overflow-hidden" 
                        style="width: {{ ($item->count / $maxCount) * 100 }}%"
                    >
                        <!-- Shine effect on the progress bar -->
                        <div class="absolute inset-0 animate-shine opacity-30"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Card Decorative Strip -->
        <div @class([
            'h-1.5 w-full mt-auto',
            'bg-gradient-to-r from-amber-300 via-amber-500 to-amber-300' => $index == 0,
            'bg-gradient-to-r from-slate-200 via-slate-400 to-slate-200' => $index == 1,
            'bg-gradient-to-r from-orange-300 via-orange-500 to-orange-300' => $index == 2,
            'bg-gray-100 dark:bg-gray-800/50' => $index > 2,
        ])></div>
    </div>
</div>
