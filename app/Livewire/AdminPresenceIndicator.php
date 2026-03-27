<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use Illuminate\Support\Facades\Cache;

class AdminPresenceIndicator extends Component
{
    public function getActiveUsersProperty(): array
    {
        $presence = Cache::get('admin:presence', []);
        $cutoff = now()->timestamp - 60;

        $active = array_filter($presence, fn ($p) => $p['last_seen'] > $cutoff);

        // Sort: admins first, then by name
        usort($active, function ($a, $b) {
            if ($a['is_admin'] !== $b['is_admin']) return $b['is_admin'] <=> $a['is_admin'];
            return strcasecmp($a['name'], $b['name']);
        });

        return array_values($active);
    }

    public function render()
    {
        return view('livewire.admin-presence-indicator', [
            'activeUsers' => $this->activeUsers,
        ]);
    }
}
