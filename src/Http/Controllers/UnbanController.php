<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Niladam\LivewireBan\Warden;

class UnbanController
{
    /**
     * No session required: the signature is the credential, and the only thing
     * this can do is remove a block. Resolved through the Warden rather than
     * route-model binding so a swapped-in model is honoured.
     */
    public function __invoke(int|string $ban, Warden $warden): View
    {
        $ban = $warden->query()->findOrFail($ban);

        if (is_null($ban->unbanned_at)) {
            $warden->unban($ban, Auth::user());
        }

        return view('livewire-ban::unbanned', ['ban' => $ban]);
    }
}
