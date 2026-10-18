<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class PosModel extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false;   // sebagian besar tabel tidak punya created_at dan updated_at
}