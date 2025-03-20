<?php

namespace Modules\Kedisiplinan\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class disiplin extends Model
{
    use HasFactory;

    protected $fillable = [];
    
    protected static function newFactory()
    {
        return \Modules\Kedisiplinan\Database\factories\DisiplinFactory::new();
    }
}
