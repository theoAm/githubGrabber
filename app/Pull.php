<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pull extends Model
{
    protected $table = 'pulls';
    public $timestamps = false;
    protected $dates = ['merged_at'];
}
