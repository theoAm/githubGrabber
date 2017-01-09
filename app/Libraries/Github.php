<?php

namespace App\Libraries;

use App\Libraries\FileLogger;
use Mockery\CountValidator\Exception;

class Github {

    protected $api_url;
    protected $access_token;
    protected $per_page;
    protected $repo_id;
    protected $repo_owner;
    protected $repo_name;
    protected $slow_process;
    protected $logger;

    function __construct($repo_owner, $repo_name, $slow_process) {

        try {

            $repo_id = \App\Repo::findRepoId($repo_owner, $repo_name);
            if(!$repo_id) {

                throw new Exception('Repo not found!');

            }

            $this->slow_process = $slow_process;
            $this->repo_id = $repo_id;
            $this->repo_owner = $repo_owner;
            $this->repo_name = $repo_name;
            $this->access_token = $_ENV['GITHUB_ACCESS_TOKEN'];
            $this->per_page = 100;
            $this->api_url = "https://api.github.com";
            $this->logger = new FileLogger();

        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }


    }

    public function fetchCommit($sha) {

        $action_url = "/repos/{$this->repo_owner}/{$this->repo_name}/commits/{$sha}";

        $params_url = "?access_token={$this->access_token}";

        $url = $this->api_url . $action_url . $params_url;

        if($this->slow_process) {

            sleep(1);

        }

        $json = $this->_request($url);

        if(!$json) {

            return FALSE;

        }

        return $this->saveCommit($json);

    }

    public function getCommits() {

        try {

            $page = 1;
            $action_url = "/repos/{$this->repo_owner}/{$this->repo_name}/commits";

            $search = TRUE;
            while($search) {

                $params_url = "?access_token={$this->access_token}"
                    . "&per_page={$this->per_page}"
                    . "&page={$page}";

                $url = $this->api_url . $action_url . $params_url;

                $json = $this->_request($url);

                if($json) {

                    echo "\nPage: " . $page . "\n";

                    foreach($json as $row) {

                        $commit = $this->saveCommit($row);

                        /**
                         * Progress
                         */
                        echo ".";


                        if($this->slow_process) {

                            sleep(1);

                        }

                    }

                } else {

                    $search = FALSE;

                }

                $page ++;
                sleep(1);

            }

        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }

    }

    public function getIssuesAndPulls() {

        try {

            $page = 1;
            $action_url = "/repos/{$this->repo_owner}/{$this->repo_name}/issues";

            $search = TRUE;
            while($search) {

                $params_url = "?access_token={$this->access_token}"
                    . "&per_page={$this->per_page}"
                    . "&page={$page}"
                    . "&state=closed";

                $url = $this->api_url . $action_url . $params_url;

                $json = $this->_request($url);

                if($json) {

                    echo "\nPage: " . $page . "\n";

                    foreach($json as $row) {

                        $issue = $this->saveIssue($row);

                        if(isset($row->pull_request)) {


                            /**
                             * Append access_token to
                             * overcome rate limit
                             */
                            $pull_url = $row->pull_request->url . "?access_token={$this->access_token}";
                            if($pull_url) {

                                $pull_row = $this->_request($pull_url);

                                if($pull_row) {

                                    $this->savePull($issue, $pull_row);

                                }

                            }

                        }

                        if($row->events_url) {

                            /**
                             *
                             * Append access_token to
                             * overcome rate limit
                             *
                             * AND pagination
                             *
                             */
                            $events_search = TRUE;
                            $events_page = 1;

                            while($events_search) {

                                $events_url = $row->events_url . "?access_token={$this->access_token}&per_page=100&page={$events_page}";
                                $issue_event_rows = $this->_request($events_url);

                                if($issue_event_rows) {

                                    foreach($issue_event_rows as $issue_event_row) {

                                        $this->saveIssueEvent($issue, $issue_event_row);

                                    }

                                } else {

                                    $events_search = FALSE;

                                }

                                $events_page ++;
                                sleep(1);

                            }

                        }

                        /**
                         * Progress
                         */
                        echo ".";

                        if($this->slow_process) {

                            sleep(1);

                        }


                    }

                } else {

                    $search = FALSE;

                }

                $page ++;
                sleep(1);

            }

        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }


    }

    private function _request($url) {

        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: seuom'
        ));

        // $output contains the output string
        $output = curl_exec($ch);

        // close curl resource to free up system resources
        curl_close($ch);

        $json = json_decode($output);
        if(isset($json->message)) {

            $this->logger->log($json->message . " - [{$url}]", $this->repo_name . "/errors.log");
            return FALSE;

        }
        return $json;

    }

    private function saveIssue($row) {

        $issue = new \App\Issue();
        $issue->repo_id = $this->repo_id;
        $issue->number = $row->number;
        $issue->state = $row->state;

        $labels = array();
        foreach($row->labels as $l) {
            $labels[] = $l->name;
        }
        $issue->labels = (count($labels)) ? implode(',', $labels) : NULL;

        $issue->title = $row->title;
        $issue->body = $row->body;
        $issue->created_by = $row->user->login;
        $issue->assignee = ($row->assignee) ? $row->assignee->login : $row->assignee;
        $issue->is_pull_request = (isset($row->pull_request)) ? TRUE : FALSE;
        $issue->created_at = \App\Libraries\DateHandler::iso8601ToUtc($row->created_at);
        $issue->updated_at = \App\Libraries\DateHandler::iso8601ToUtc($row->updated_at);
        $issue->closed_at = \App\Libraries\DateHandler::iso8601ToUtc($row->closed_at);
        $issue->closed_by = NULL;
        $issue->save();
        return $issue;

    }

    private function savePull(\App\Issue $issue, $pull_row) {

        $pull = new \App\Pull();
        $pull->repo_id = $this->repo_id;
        $pull->issue_id = $issue->id;
        $pull->number = $pull_row->number;
        $pull->is_merged = $pull_row->merged;
        $pull->merged_at = ($pull_row->merged && $pull_row->merged_at) ? \App\Libraries\DateHandler::iso8601ToUtc($pull_row->merged_at) : NULL;
        $pull->merged_by = ($pull_row->merged && $pull_row->merged_by) ? $pull_row->merged_by->login : NULL;
        $pull->commits_count = $pull_row->commits;
        $pull->additions = $pull_row->additions;
        $pull->deletions = $pull_row->deletions;
        $pull->changed_files_count = $pull_row->changed_files;
        $pull->save();
        return $pull;

    }

    private function saveIssueEvent(\App\Issue $issue, $issue_event_row) {

        $issueEvent = new \App\IssueEvent();
        $issueEvent->repo_id = $this->repo_id;
        $issueEvent->issue_id = $issue->id;
        $issueEvent->issue_number = $issue->number;
        $issueEvent->github_id = $issue_event_row->id;
        $issueEvent->actor = ($issue_event_row->actor) ? $issue_event_row->actor->login : $issue_event_row->actor;
        $issueEvent->event_description = $issue_event_row->event;
        $issueEvent->commit_sha = $issue_event_row->commit_id;
        $issueEvent->created_at = \App\Libraries\DateHandler::iso8601ToUtc($issue_event_row->created_at);
        $issueEvent->save();
        return $issueEvent;

    }

    private function saveCommit($row) {

        $commit = new \App\Commit();
        $commit->repo_id = $this->repo_id;
        $commit->pull_id = NULL;
        $commit->sha = $row->sha;
        $commit->author = $row->commit->author->name;
        $commit->committer = $row->commit->committer->name;
        $commit->message = $row->commit->message;
        $commit->authored_at = \App\Libraries\DateHandler::iso8601ToUtc($row->commit->author->date);
        $commit->committed_at = \App\Libraries\DateHandler::iso8601ToUtc($row->commit->committer->date);
        $commit->save();


        /**
         * Append access_token to
         * overcome rate limit
         */
        $commit_url = $row->url . "?access_token={$this->access_token}";
        $json_commit_extras = $this->_request($commit_url);

        if($json_commit_extras) {

            $this->saveCommitStat($commit, $json_commit_extras);

            $this->saveCommitFile($commit, $json_commit_extras);

        }

        return $commit;

    }

    private function saveCommitStat(\App\Commit $commit, $json_commit_extras) {

        $commitStat = new \App\CommitStat();
        $commitStat->repo_id = $this->repo_id;
        $commitStat->commit_id = $commit->id;
        $commitStat->additions = $json_commit_extras->stats->additions;
        $commitStat->deletions = $json_commit_extras->stats->deletions;
        $commitStat->total = $json_commit_extras->stats->total;
        $commitStat->save();

    }

    private function saveCommitFile(\App\Commit $commit, $json_commit_extras) {

        $files = $json_commit_extras->files;
        if(count($files)) {

            foreach($files as $file_row) {

                $commitFile = new \App\CommitFile();
                $commitFile->repo_id = $this->repo_id;
                $commitFile->commit_id = $commit->id;
                $commitFile->filename = $file_row->filename;
                $commitFile->additions = $file_row->additions;
                $commitFile->deletions = $file_row->deletions;
                $commitFile->changes = $file_row->changes;
                $commitFile->status = $file_row->status;
                $commitFile->save();

            }

        }

    }

}
