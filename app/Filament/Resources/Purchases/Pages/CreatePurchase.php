<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = app(PurchaseService::class)->create($data, auth()->id());
        } catch (Throwable $e) {
            Notification::make()->title('Pembelian gagal disimpan')->body($e->getMessage())->danger()->send();
            $this->halt();
        }

        return Purchase::findOrFail($id);
    }
}