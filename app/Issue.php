<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{

    protected $table = 'issues';
    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at', 'closed_at'];

    public function events() {

        return \App\IssueEvent::where('issue_id', '=', $this->id)->orderBy('created_at', 'asc')->get();

    }

}
