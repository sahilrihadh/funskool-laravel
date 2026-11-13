<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * In-panel notifications (the bell in the admin header) for staff who hold
 * a given permission, e.g. "support.view" for new tickets.
 */
class StaffNotifier
{
    /**
     * @param  iterable<User>|null  $recipients  explicit recipients instead of everyone with $ability
     */
    public function notify(string $ability, string $title, ?string $body = null, ?string $url = null, string $icon = 'heroicon-o-bell', string $color = 'primary', ?iterable $recipients = null): void
    {
        $users = $recipients !== null ? collect($recipients) : $this->staffWith($ability);

        if ($users->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->iconColor($color);

        if ($url) {
            $notification->actions([
                Action::make('view')->label('View')->url($url)->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($users);
    }

    /** @return Collection<int, User> */
    public function staffWith(string $ability): Collection
    {
        return User::query()
            ->whereIn('role', UserRole::staffValues())
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($ability))
            ->values();
    }
}
