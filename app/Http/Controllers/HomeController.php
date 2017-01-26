<?php

namespace App\Http\Controllers;

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
    protected $slow_process;
    protected $github;
    protected $reporter;

    function __construct()
    {
        $this->repo_owner = "illuminate";
        $this->repo_name = "queue";
        $this->slow_process = true;
        $this->github = new Github($this->repo_owner, $this->repo_name, $this->slow_process);
        $this->reporter = new Reporter($this->repo_owner, $this->repo_name, $this->slow_process);
    }

    function home()
    {
        echo 'exiting...';exit;
        $issue = \App\Issue::find(51645);
        $repo_owner = $this->repo_owner;
        $repo_name = $this->repo_name;
        $slow_process = true;
        $reporter = new \App\Libraries\Reporter($repo_owner, $repo_name, $slow_process);
        $reporter->processIssue($issue);

    }

    function fetchGithubData()
    {
        echo 'exiting...';exit;
        $this->github->getIssuesAndPulls();
        $this->github->getCommits();
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
        //echo 'exiting...';exit;
        $this->reporter->compareCommitsTd();
    }

}
