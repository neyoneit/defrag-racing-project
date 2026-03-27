<div wire:poll.5s class="flex items-center gap-2">
    @foreach($activeUsers as $user)
        @php
            $secondsAgo = now()->timestamp - $user['last_seen'];
            if ($secondsAgo < 30) {
                $status = 'live';
                $dotColor = '#22c55e';
                $borderClass = 'border-green-500/30';
                $bgClass = 'bg-green-500/10';
                $badgeText = 'LIVE';
                $badgeBg = '#22c55e';
                $badgeColor = '#fff';
            } elseif ($secondsAgo < 120) {
                $status = 'idle';
                $dotColor = '#eab308';
                $borderClass = 'border-yellow-500/30';
                $bgClass = 'bg-yellow-500/10';
                $badgeText = 'IDLE';
                $badgeBg = '#eab308';
                $badgeColor = '#000';
            } else {
                $status = 'away';
                $dotColor = '#6b7280';
                $borderClass = 'border-gray-500/30';
                $bgClass = 'bg-gray-500/10';
                $badgeText = 'AWAY';
                $badgeBg = '#4b5563';
                $badgeColor = '#d1d5db';
            }
        @endphp
        <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg {{ $bgClass }} border {{ $borderClass }}">
            <div class="relative flex-shrink-0">
                <img
                    src="{{ $user['avatar'] ? '/storage/' . $user['avatar'] : '/images/null.jpg' }}"
                    alt=""
                    class="w-6 h-6 rounded-full object-cover"
                    style="box-shadow: 0 0 0 2px {{ $dotColor }}40;"
                />
                <div class="{{ $status === 'live' ? 'animate-pulse' : '' }}" style="position:absolute; bottom:-2px; right:-2px; width:10px; height:10px; border-radius:50%; border:2px solid #111827; background-color:{{ $dotColor }};"></div>
            </div>
            <div class="flex flex-col leading-none gap-0.5">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold">{!! \App\Filament\Resources\UserResource::q3tohtml($user['name']) !!}</span>
                    <span class="px-1 py-[1px] rounded text-[8px] font-black uppercase leading-none" style="background-color: {{ $badgeBg }}; color: {{ $badgeColor }};">{{ $badgeText }}</span>
                </div>
                <span class="text-[10px] text-gray-400">{{ $user['activity'] }}</span>
            </div>
        </div>
    @endforeach

    @if(count($activeUsers) === 0)
        <span class="text-xs text-gray-500 italic">No staff online</span>
    @endif
</div>
