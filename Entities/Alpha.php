<?php

namespace Modules\Kedisiplinan\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Alpha extends Model
{
    use HasFactory;

    protected $connection = 'second_db';
    protected $table = 'presensi';
    protected $primaryKey = 'id';
    protected $fillable = [];
}
