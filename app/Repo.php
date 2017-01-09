<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Repo extends Model
{

    protected $table = 'repos';
    public $timestamps = false;

    public static function findRepoId($repo_owner, $repo_name) {

        $where = array(
            'owner' => $repo_owner,
            'name' => $repo_name
        );
        $query = \App\Repo::where($where)->first();
        if($query) {
            return $query->toArray()['id'];
        }
        return FALSE;

    }

    public function issues() {

        return \App\Issue::where('repo_id', '=', $this->id)
            ->where('is_pull_request', '=', 0)
            ->where('state', '=', 'closed')
            ->orderBy('number', 'asc')
            ->get();

    }


}
