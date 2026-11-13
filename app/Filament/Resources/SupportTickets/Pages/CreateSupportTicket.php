<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\User;
use App\Services\SupportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = ! empty($data['user_id']) ? User::query()->find($data['user_id']) : User::query()->where('email', strtolower($data['email']))->first();

        return app(SupportService::class)->open($data, $user, source: 'staff', openedByStaff: auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
