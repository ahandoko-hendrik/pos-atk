<?php

namespace App\Models;

class ProductPrice extends PosModel
{
    public function tier()
    {
        return $this->belongsTo(PriceTier::class, 'tier_id');
    }
}