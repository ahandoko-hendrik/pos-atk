<?php

namespace App\Models;

class Purchase extends PosModel
{
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}