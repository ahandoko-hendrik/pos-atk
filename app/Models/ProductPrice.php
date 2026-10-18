<?php

namespace App\Models;


class ProductPrice extends PosModel
{
    public $timestamps = true;   // tabel products punya created_at dan updated_at

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }
}
