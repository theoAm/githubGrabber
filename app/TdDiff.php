<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TdDiff extends Model
{
    public function violations()
    {
        return $this->hasMany('App\TdViolation');
    }
}
