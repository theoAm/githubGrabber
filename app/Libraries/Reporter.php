<?php

namespace App\Libraries;

use App\Commit;
use App\CommitFile;
use App\Interfaces\Reporting;
use App\Libraries\FileLogger;
use App\Repo;
use Illuminate\Support\Facades\DB;
use Mockery\CountValidator\Exception;

class Reporter implements Reporting {

    protected $slow_process;
    protected $repo_owner;
    protected $repo_name;
    protected $github;
    protected $logger;

    function __construct($repo_owner, $repo_name, $slow_process) {

        try {

            $repo_id = \App\Repo::findRepoId($repo_owner, $repo_name);
            if(!$repo_id) {

                throw new Exception('Repo not found!');

            }

            $this->slow_process = $slow_process;
            $this->repo_owner = $repo_owner;
            $this->repo_name = $repo_name;
            $this->github = new Github($this->repo_owner, $this->repo_name, $this->slow_process);
            $this->logger = new FileLogger();

        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }


    }

    public function analyzeData() {

        try {

            $repo_id = \App\Repo::findRepoId($this->repo_owner, $this->repo_name);
            if(!$repo_id) {

                throw new Exception('Repo not found');

            }

            $repo = \App\Repo::find($repo_id);
            $issues = $repo->issues();

            if($issues->isEmpty()) {

                throw new Exception('No issues found');

            }

            /** @var \App\Issue $issue */
            $i = 0;
            foreach ($issues as $issue) {

                $i ++;

                $this->processIssue($issue);

                echo ($i % 10 === 0) ? $i : ".";

            }

            /**
             * Separator line
             */
            $this->logger->log("#####", $this->repo_name . '/report.log');
            $this->logger->log("#####", $this->repo_name . '/errors.log');


        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }

    }

    public function processIssue(\App\Issue $issue) {

        $events = $issue->events();

        if($events->isEmpty()) {

            return FALSE;

        }

        list($assigned, $referenced, $closed) = $this->processEvents($events);

        if(!count($referenced) && !count($closed)) {

            return FALSE;

        }

        $close_date = NULL;
        $last = NULL;
        if(count($closed)) {

            $last = array_pop($closed);
            $close_date = $last->created_at;

        } elseif(count($referenced)) {

            $last = array_pop($referenced);
            $close_date = $last->created_at;

        }

        if(!$close_date) {

            return FALSE;

        }

        $start_date = $issue->created_at;
        if(count($assigned)) {

            $tmp_start_date = $assigned[0]->created_at;
            if($tmp_start_date->lt($close_date) && $tmp_start_date->gt($start_date)) {

                $start_date = $tmp_start_date;

            }
        }

        if(!$start_date) {

            return FALSE;

        }


        if($start_date->gt($close_date)) {

            return FALSE;

        }

        $commit = $last->commit();
        if(!$commit) {

            return FALSE;

        }

        /**
         * Find the release to which the
         * issue belongs
         */
        $release = $this->getIssueRelease($issue);
        if(!$release) {

            return FALSE;

        }

        $files = $commit->commitFiles();
        $files_count = $files->count();
        if (!$files_count) {

            return FALSE;

        }

        foreach($files as $file) {

            /**
             * Is it a .php file?
             */
            if(!$this->isPhpFile($file->filename)) {
                continue;
            }

            /**
             * Get TD index from SonarQube
             */
            $sonar_metrics = $this->getMetricsFromSonarQube($file, $release);
            if(!$sonar_metrics) {
                continue;
            }

            /**
             * Log results
             */
            $report = $this->repo_name . ','
                    . $release . ','
                    . $issue->id . ','
                    . $commit->sha . ','
                    . $start_date . ','
                    . $close_date . ','
                    . $start_date->diffInSeconds($close_date) . ','
                    . $files_count . ','
                    . $file->filename . ','
                    . $file->changes . ','
                    . $sonar_metrics['sqale_index'] . ','
                    . $sonar_metrics['sqale_debt_ratio'] . ','
                    . $sonar_metrics['blocker_violations'] . ','
                    . $sonar_metrics['critical_violations'] . ','
                    . $sonar_metrics['major_violations'] . ','
                    . $sonar_metrics['minor_violations'] . ','
                    . $sonar_metrics['info_violations'];
            $this->logger->log($report, $this->repo_name . '/report.log');

        }


    }

    private function processEvents($events) {

        $assigned = array();
        $referenced = array();
        $closed = array();

        /** @var \App\IssueEvent $event */
        foreach ($events as $event) {

            switch ($event->event_description) {

                case 'assigned':
                    $assigned[] = $event;
                    break;

                case 'referenced':
                    if($event->commit_sha) {

                        /** @var \App\Commit $commit */
                        $commit = $event->commit();
                        if(!$commit) {

                            /**
                             * Commit was not found in the database
                             * It means that this commit belongs
                             * to another branch from which
                             * we did not fetch data
                             *
                             * Fetch data directly from Github
                             */
                            $commit = $event->fetchMissingCommit($this->github);

                        }

                        /**
                         * SEARCH FOR CLOSING regex expression
                         * in commit message
                         */
                        if($commit) {
                            $pattern = "/(fix|fixes|fixed|close|closes|closed|resolve|resolves|resolved) (#\d+)/i";
                            if(preg_match($pattern, $commit->message)) {

                                $referenced[] = $event;

                            }
                        }

                    }
                    break;

                case 'closed':
                    if($event->commit_sha) {
                        $closed[] = $event;
                    }
                    break;

            }

        }

        return array($assigned, $referenced, $closed);
    }

    private function getIssueRelease(\App\Issue $issue) {

        $row = DB::table('repo_releases')
            ->where('repo_id', '=', $issue->repo_id)
            ->where('released_at', '<', $issue->created_at)
            ->orderBy('released_at', 'desc')
            ->first();

        if(!$row) {
            return FALSE;
        }

        $release = $row->name;
        $release = str_replace('.', '', $release);
        return $release;

    }

    private function getMetricsFromSonarQube(\App\CommitFile $file, $release) {

        $metricKeys = 'sqale_index,sqale_debt_ratio,blocker_violations,critical_violations,major_violations,minor_violations,info_violations';
        $componentKey = $this->repo_owner . $this->repo_name . $release . ':' . $file->filename;
        $url = 'http://' . $_ENV['SONARQUBE_HOST'] . '/api/measures/component?metricKeys=' . $metricKeys . '&componentKey=' . $componentKey;

        try {

            $json = json_decode(file_get_contents($url));

            /**
             * SONAR returns TD in minutes
             */
            if(!isset($json->component) || !isset($json->component->measures)) {

                throw new \Exception("Could not fetch TD for component: " . $componentKey);

            }

            $sonar_metrics = array();

            $measures = $json->component->measures;
            foreach ($measures as $measure) {

                $metric = $measure->metric;
                $value = $measure->value;

                $sonar_metrics[$metric] = $value;

            }

            return $sonar_metrics;


        } catch (\Exception $ex) {

            $this->logger->log($ex->getMessage(), $this->repo_name . "/errors.log");
            return FALSE;

        }

    }

    /**
     * @param $filename (full path)
     */
    private function isPhpFile($filename) {

        $sub_str = substr($filename, -4);
        if($sub_str == '.php') {

            return true;

        } else {

            return false;

        }

    }

    private function csvRowHasData($row) {

        if($row == '#####' || $row == '') {
            return false;
        }
        return true;

    }

    public function parseDataForFiles() {

        $file = app_path() . '/Logs/' . $this->repo_name . '/report.log';
        $content = file_get_contents($file);
        $rows = explode("\n", $content);

        if(!count($rows)) {
            return false;
        }

        $data = array();
        foreach ($rows as $row) {

            if(!$this->csvRowHasData($row)) {

                continue;

            }

            $tmp = explode(',', $row);
            $filename = $tmp[8];                    //filename
            $filechanges = $tmp[9];                 //file changes
            $sqale_index = $tmp[10];                //sqale_index
            $sqale_debt_ratio = $tmp[11];           //sqale_debt_ratio
            $blocker_violations = $tmp[12];         //blocker_violations
            $critical_violations = $tmp[13];        //critical_violations
            $major_violations = $tmp[14];           //major_violations
            $minor_violations = $tmp[15];           //minor_violations
            $info_violations = $tmp[16];            //info_violations

            if(isset($data[$filename])) {

                $data[$filename]['count']++;

                $data[$filename]['changes'] = $data[$filename]['changes'] + $filechanges;
                $data[$filename]['avg_changes'] = round($data[$filename]['changes'] / $data[$filename]['count'],2);

                $data[$filename]['sqale_index'] = $data[$filename]['sqale_index'] + $sqale_index;
                $data[$filename]['avg_sqale_index'] = round($data[$filename]['sqale_index'] / $data[$filename]['count'],2);

                $data[$filename]['sqale_debt_ratio'] = $data[$filename]['sqale_debt_ratio'] + $sqale_debt_ratio;
                $data[$filename]['avg_sqale_debt_ratio'] = round($data[$filename]['sqale_debt_ratio'] / $data[$filename]['count'],3);

                $data[$filename]['blocker_violations'] = $data[$filename]['blocker_violations'] + $blocker_violations;
                $data[$filename]['avg_blocker_violations'] = round($data[$filename]['blocker_violations'] / $data[$filename]['count'],2);

                $data[$filename]['critical_violations'] = $data[$filename]['critical_violations'] + $critical_violations;
                $data[$filename]['avg_critical_violations'] = round($data[$filename]['critical_violations'] / $data[$filename]['count'],2);

                $data[$filename]['major_violations'] = $data[$filename]['major_violations'] + $major_violations;
                $data[$filename]['avg_major_violations'] = round($data[$filename]['major_violations'] / $data[$filename]['count'],2);

                $data[$filename]['minor_violations'] = $data[$filename]['minor_violations'] + $minor_violations;
                $data[$filename]['avg_minor_violations'] = round($data[$filename]['minor_violations'] / $data[$filename]['count'],2);

                $data[$filename]['info_violations'] = $data[$filename]['info_violations'] + $info_violations;
                $data[$filename]['avg_info_violations'] = round($data[$filename]['info_violations'] / $data[$filename]['count'],2);

            } else {

                $data[$filename] = array(
                    'count' => 1,

                    'changes' => $filechanges,
                    'avg_changes' => $filechanges,

                    'sqale_index' => $sqale_index,
                    'avg_sqale_index' => $sqale_index,

                    'sqale_debt_ratio' => $sqale_debt_ratio,
                    'avg_sqale_debt_ratio' => $sqale_debt_ratio,

                    'blocker_violations' => $blocker_violations,
                    'avg_blocker_violations' => $blocker_violations,

                    'critical_violations' => $critical_violations,
                    'avg_critical_violations' => $critical_violations,

                    'major_violations' => $major_violations,
                    'avg_major_violations' => $major_violations,

                    'minor_violations' => $minor_violations,
                    'avg_minor_violations' => $minor_violations,

                    'info_violations' => $info_violations,
                    'avg_info_violations' => $info_violations,
                );

            }

        }


        if(!count($data)) {

            return false;

        }

        foreach ($data as $filename => $array) {

            $str = $filename . ','
                . $array['count'] . ','
                . $array['avg_changes'] . ','
                . $array['avg_sqale_index'] . ','
                . $array['avg_sqale_debt_ratio'] . ','
                . $array['avg_blocker_violations'] . ','
                . $array['avg_critical_violations'] . ','
                . $array['avg_major_violations'] . ','
                . $array['avg_minor_violations'] . ','
                . $array['avg_info_violations'];
            $this->logger->log($str, $this->repo_name . '/files2.log');

        }

        $this->logger->log("#####", $this->repo_name . '/files2.log');
        echo "FINISHED1\n";

    }

    public function parseDataForIssues() {

        $file = app_path() . '/Logs/' . $this->repo_name . '/report.log';
        $content = file_get_contents($file);
        $rows = explode("\n", $content);

        if(!count($rows)) {
            return false;
        }

        $data = array();
        foreach ($rows as $row) {

            if(!$this->csvRowHasData($row)) {

                continue;

            }

            $tmp = explode(',', $row);
            $issue_id = $tmp[2];                    //issue id
            $diff_seconds = $tmp[6];                //resolution time in seconds
            $sqale_index = $tmp[10];                //sqale_index

            if(isset($data[$issue_id])) {

                $old_sqale_index = $data[$issue_id]['sqale_index'];
                $old_count = $data[$issue_id]['count'];
                $data[$issue_id]['sqale_index'] = $old_sqale_index + $sqale_index;
                $data[$issue_id]['count'] = $old_count + 1;

            } else {

                $data[$issue_id] = array(
                    'resolution_time' => $diff_seconds,
                    'sqale_index' => $sqale_index,
                    'count' => 1
                );

            }

        }


        if(!count($data)) {

            return false;

        }

        foreach ($data as $issue_id => $array) {

            $str = $issue_id . ','
                . $array['resolution_time'] . ','
                . $array['sqale_index'] / $array['count'];
            $this->logger->log($str, $this->repo_name . '/issues2.log');

        }

        $this->logger->log("#####", $this->repo_name . '/issues2.log');
        echo "FINISHED2\n";

    }



    /**
     * *************************************************************8
     */
    public function compareCommitsTd()
    {
        $repo = Repo::where('name', $this->repo_name)
            ->where('owner', $this->repo_owner)
            ->first();

        $committers = DB::table('commits')
            ->select('committer', DB::raw('COUNT(*) as count'))
            ->where('repo_id', $repo->id)
            ->groupBy('committer')
            ->havingRaw('COUNT(*) >= 10')
            ->orderBy('count', 'DESC')
            ->get();

        $progress = 0;

        if($committers) {

            foreach ($committers as $json) {

                $commits = Commit::where('committer', $json->committer)
                    ->where('repo_id', $repo->id)
                    ->orderBy('committed_at', 'ASC')
                    ->get();
                if(!$commits->count()) {
                    continue;
                }
                foreach ($commits as $commit) {

                    $progress ++;

                    $previous_commit = Commit::where('committed_at', '<', $commit->committed_at)
                        ->where('repo_id', $repo->id)
                        ->latest('committed_at')
                        ->first();
                    if(!$previous_commit) {
                        continue;
                    }

                    $commit_files = CommitFile::where('commit_id', $commit->id)
                        ->where('repo_id', $repo->id)
                        ->where('filename', 'like', '%.php')
                        ->get();
                    if(!$commit_files) {
                        continue;
                    }

                    foreach ($commit_files as $commit_file) {

                        $metrics = $this->getFileMetricsFromSonarQube($commit_file, $commit->sha);
                        if(!$metrics) {
                            continue;
                        }

                        $previous_metrics = $this->getFileMetricsFromSonarQube($commit_file, $previous_commit->sha);
                        if(!$previous_metrics) {
                            $previous_metrics = [
                                "minor_violations" => "0",
                                "info_violations" => "0",
                                "major_violations" => "0",
                                "sqale_debt_ratio" => "0",
                                "blocker_violations" => "0",
                                "critical_violations" => "0",
                                "sqale_index" => "0",
                            ];
                        }

                        $violations_added_str = '';
                        $violations_resolved_str = '';

                        if(
                            $metrics['info_violations'] ||
                            $metrics['minor_violations'] ||
                            $metrics['major_violations'] ||
                            $metrics['critical_violations'] ||
                            $metrics['blocker_violations']
                        ) {

                            $violations = $this->getFileBrokenRulesFromSonarQube($commit_file, $commit->sha);
                            $previous_violations = $this->getFileBrokenRulesFromSonarQube($commit_file, $previous_commit->sha);

                            $violations_added = [];
                            $violations_resolved = [];

                            /**
                             * SEARCH FOR VIOLATIONS ADDED
                             */
                            foreach ($violations as $nvkey => $nvvalue) {
                                if(!array_key_exists($nvkey, $previous_violations)) {
                                    $violations_added[$nvkey] = $nvvalue;
                                }
                            }
                            $tmp = [];
                            if($violations_added) {
                                foreach ($violations_added as $vakey => $vavalue) {
                                    $tmp[] = $vakey . '---' . $vavalue;
                                }
                                $violations_added_str = implode('|||', $tmp);
                            }

                            /**
                             * SEARCH FOR VIOLATIONS RESOLVED
                             */
                            foreach ($previous_violations as $pvkey => $pvvalue) {
                                if(!array_key_exists($pvkey, $violations)) {
                                    $violations_resolved[$pvkey] = $pvvalue;
                                }
                            }
                            $tmp = [];
                            if($violations_resolved) {
                                foreach ($violations_resolved as $vrkey => $vrvalue) {
                                    $tmp[] = $vrkey . '---' . $vrvalue;
                                }
                                $violations_resolved_str = implode('|||', $tmp);
                            }

                        }

                        $comparison = [
                            "minor_violations_diff" => intval($metrics["minor_violations"]) - intval($previous_metrics["minor_violations"]),
                            "info_violations_diff" => intval($metrics["info_violations"]) - intval($previous_metrics["info_violations"]),
                            "major_violations_diff" => intval($metrics["major_violations"]) - intval($previous_metrics["major_violations"]),
                            "sqale_debt_ratio_diff" => floatval($metrics["sqale_debt_ratio"]) - floatval($previous_metrics["sqale_debt_ratio"]),
                            "blocker_violations_diff" => intval($metrics["blocker_violations"]) - intval($previous_metrics["blocker_violations"]),
                            "critical_violations_diff" => intval($metrics["critical_violations"]) - intval($previous_metrics["critical_violations"]),
                            "sqale_index_diff" => floatval($metrics["sqale_index"]) - floatval($previous_metrics["sqale_index"]),
                            "violation_added" => $violations_added_str,
                            "violation_resolved" => $violations_resolved_str,
                        ];


                        /**
                         * EDOOOOOOOOOOOOOOO
                         *
                         * anti na to logaro se arxeio na to eisago stin vash
                         */

                        /*$report = $this->repo_name . ','
                            . $commit->committer . ','
                            . $commit->sha . ','
                            . $comparison['sqale_index_diff'] . ','
                            . $comparison['sqale_debt_ratio_diff'] . ','
                            . $comparison['blocker_violations_diff'] . ','
                            . $comparison['critical_violations_diff'] . ','
                            . $comparison['major_violations_diff'] . ','
                            . $comparison['minor_violations_diff'] . ','
                            . $comparison['info_violations_diff'];
                        $this->logger->log($report, $this->repo_name . '/commitsTD.log');*/
                    }

                    echo ($progress % 10 == 0) ? $progress : ".";
                }

            }

        }
    }

    private function getFileMetricsFromSonarQube(\App\CommitFile $file, $sha) {

        $metricKeys = 'sqale_index,sqale_debt_ratio,blocker_violations,critical_violations,major_violations,minor_violations,info_violations';
        $componentKey = $this->repo_name . ':' . $sha . ':' . $file->filename;
        $url = 'http://' . $_ENV['SONARQUBE_HOST'] . '/api/measures/component?metricKeys=' . $metricKeys . '&componentKey=' . $componentKey;

        try {

            $json = json_decode(file_get_contents($url));

            /**
             * SONAR returns TD in minutes
             */
            if(!isset($json->component) || !isset($json->component->measures)) {

                throw new \Exception("Could not fetch TD for component: " . $componentKey);

            }

            $sonar_metrics = array();

            $measures = $json->component->measures;
            foreach ($measures as $measure) {

                $metric = $measure->metric;
                $value = $measure->value;

                $sonar_metrics[$metric] = $value;

            }

            return $sonar_metrics;


        } catch (\Exception $ex) {

            $this->logger->log($ex->getMessage(), $this->repo_name . "/errors.log");
            return FALSE;

        }

    }

    private function getFileBrokenRulesFromSonarQube(\App\CommitFile $file, $sha) {

        $componentKey = $this->repo_name . ':' . $sha . ':' . $file->filename;
        $url = 'http://' . $_ENV['SONARQUBE_HOST'] . '/api/issues/search?componentKeys=' . $componentKey;

        try {

            $rules_broken = [];

            $json = json_decode(file_get_contents($url));

            if(!$json->total) {
                return $rules_broken;
            }

            foreach ($json->issues as $i) {
                $rules_broken[$i->rule] = '';
            }

            foreach ($rules_broken as $rkey => $rvalue) {
                $rule_info = $this->getRuleInfoFromSonarQube($rkey);
                if(!$rule_info) {
                    continue;
                }
                $rules_broken[$rkey] = $rule_info['name'];
            }

            return $rules_broken;

        } catch (\Exception $ex) {

            $this->logger->log($ex->getMessage(), $this->repo_name . "/errors.log");
            return [];

        }

    }

    private function getRuleInfoFromSonarQube($rule_key)
    {
        $url = 'http://' . $_ENV['SONARQUBE_HOST'] . '/api/rules/search?rule_key=' . $rule_key;
        $json = json_decode(file_get_contents($url));
        if(!$json->total) {
            return false;
        }
        $rule = $json->rules[0];
        return [
            'name' => $rule->name,
            'severity' => $rule->severity,
            'defaultDebtChar' => $rule->defaultDebtChar
        ];
    }

}