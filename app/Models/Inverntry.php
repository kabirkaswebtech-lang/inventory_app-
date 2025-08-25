<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inverntry extends Model
{
    protected $table = 'inventory';

    // If you want to allow mass assignment
    protected $fillable = ['sku','Available','upc_code'];
}
