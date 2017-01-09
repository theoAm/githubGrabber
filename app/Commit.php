<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Commit extends Model
{
    protected $table = 'commits';
    public $timestamps = false;
    protected $dates = ['authored_at', 'committed_at'];


    public function commitFiles() {

        return \App\CommitFile::where('commit_id', '=', $this->id)->where('repo_id', '=', $this->repo_id)->get();

    }

}
