<?php

namespace App\Http\Controllers;

use App\Commit;
use App\Libraries\FileLogger;
use App\Libraries\Github;
use App\Libraries\Reporter;
use App\Repo;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\DB;
use Mockery\CountValidator\Exception;

class HomeController extends Controller
{

    protected $repo_owner;
    protected $repo_name;
    protected $repo_branch;
    protected $since;
    protected $until;
    protected $slow_process;
    protected $github;
    protected $reporter;

    function __construct()
    {
        $this->repo_owner = $_ENV['REPO_OWNER'];
        $this->repo_name = $_ENV['REPO_NAME'];
        $this->repo_branch = $_ENV['REPO_BRANCH'];
        $this->since = $_ENV['REPO_COMMITS_SINCE'];
        $this->until = $_ENV['REPO_COMMITS_UNTIL'];
        $this->slow_process = true;
        $this->github = new Github($this->repo_owner, $this->repo_name, $this->slow_process);
        $this->reporter = new Reporter($this->repo_owner, $this->repo_name, $this->slow_process);
    }

    function home()
    {
        dd('Exiting...');
        $commit = Commit::find(80);
        $previous_commit = Commit::where('committed_at', '<', $commit->committed_at)
            ->where('repo_id', $commit->repo_id)
            ->latest('committed_at')
            ->first();

        dd($previous_commit);

    }

    function fetchGithubData()
    {
        echo 'exiting...';exit;
        //$this->github->getIssuesAndPulls();
        $this->github->getCommits($this->repo_branch, $this->since, $this->until);
    }

    function analyzeGithubData()
    {
        echo 'exiting...';exit;
        $this->reporter->analyzeData();

    }

    function parseGithubData()
    {
        echo 'exiting...';exit;
        $this->reporter->parseDataForFiles();
        $this->reporter->parseDataForIssues();

    }

    public function compareCommitsTd()
    {
        echo 'exiting...';exit;
        $this->reporter->compareCommitsTd();
    }

}
