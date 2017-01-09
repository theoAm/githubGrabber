<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IssueEvent extends Model
{

    protected $table = 'issue_events';
    public $timestamps = false;
    protected $dates = ['created_at'];


    public function commit() {

        return \App\Commit::where('sha', '=', $this->commit_sha)->where('repo_id', '=', $this->repo_id)->first();

    }

    public function fetchMissingCommit(\App\Libraries\Github $github) {

        return $github->fetchCommit($this->commit_sha);

    }
}
