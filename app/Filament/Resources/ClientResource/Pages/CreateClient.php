<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Laravel\Passport\ClientRepository;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $clients = app(ClientRepository::class);

        // Assume standard "Confidential" client (Authorization Code Grant)
        // User ID null usually means it's a general client, or user_id = current user?
        // For SSO app, usually the client belongs to no specific user or the admin.
        // Let's use null for user_id to imply "System Client" or assign to Auth user.
        // Standard passport:client command uses null for user_id unless --user is passed.

        $userId = null;
        $name = $data['name'];
        $redirect = $data['redirect'];
        $confidential = true; // Use secret

        $client = $clients->createAuthorizationCodeGrantClient(
            $name,
            [$redirect], // Expects array of implementation URIs
            $confidential,
            $userId // null
        );

        // Store the PLAIN secret temporarily to show it
        session()->flash('client_secret', $client->plainSecret);

        return $client;
    }

    protected function getCreatedNotification(): ?Notification
    {
        $secret = session('client_secret');

        return Notification::make()
            ->success()
            ->title('Client created')
            ->body("IMPORTANT: Copy this secret now. It will not be shown again.\n\nClient ID: " . $this->record->id . "\nSecret: " . $secret)
            ->persistent()
            ->duration(null); // Keep until dismissed
    }
}
