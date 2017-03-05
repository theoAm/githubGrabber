<?php

namespace App\Http\Controllers;

use App\Commit;
use App\Repo;
use App\TdDiff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ResultsController extends Controller
{
    function index()
    {
        $items = TdDiff::select('repo_id')->distinct()->get();
        if(!$items->count()) {
            echo 'No repos found!';
            exit;
        }

        echo "<h2><u>Repositories:</u></h2>";
        foreach ($items as $item) {
            $repo = Repo::find($item->repo_id);
            echo "<div><a href='/results/td/{$repo->id}'>{$repo->owner}/{$repo->name}</a></div>";
        }
    }

    function td(Repo $repo)
    {
        $data = [
            'repo' => $repo,
        ];
        return view('results.td', $data);

    }

    function rq1(Repo $repo)
    {
        try {

            $resp = [];
            $resp['rq'] = 'RQ1: Do developers cause equal harm to TD?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('author', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->where('author', '<>', 'n/a')
                ->groupBy('author')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $datapoints = [];

            $counter = 0;
            foreach ($items as $item) {

                $counter++;

                $changed_loc = DB::table('commits')
                    ->join('commit_stats', 'commits.id', '=', 'commit_stats.commit_id')
                    ->where('commits.repo_id', $repo->id)
                    ->where('commits.author', $item->author)
                    ->select(DB::raw('SUM(commit_stats.total) as sum'))
                    ->first()->sum;

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $sqale_index_diff = 0;
                foreach ($tdDiffs as $tdDiff) {

                    $sqale_index_diff += $tdDiff->sqale_index_diff;

                }

                $resp['labels'][] = 'Developer ' . $counter;
                $datapoints[] = $sqale_index_diff / $changed_loc;

            }

            $resp['datasets'][] = [
                'label' => "TD added (in minutes) per changed LOC",
                'data' => $datapoints,
                'backgroundColor' => '#e5690b',
            ];

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }

    function rq2(Repo $repo)
    {
        try {

            $resp = [];
            $resp['rq'] = 'RQ2: What kind of violations do developers add?';
            $resp['heatMap'] = [];

            $items = TdDiff::select('author', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->where('author', '<>', 'n/a')
                ->groupBy('author')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $w = [];
            $x = [];
            $y = [];
            $t = [];
            $z = [];
            $x_anonymous = [];

            $counter = 0;

            foreach ($items as $item) {

                $counter++;

                $t[$item->author] = 0;

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $x[$item->author] = $item->author;
                $x_anonymous[] = 'Developer ' . $counter;

                foreach ($tdDiffs as $tdDiff) {

                    $violations = $tdDiff->violations;
                    if($violations->count()) {

                        foreach ($violations as $violation) {

                            $t[$item->author]++;

                            if($violation->added_or_resolved == 'added') {

                                $y[$violation->key] = $violation->key;
                                $w[$violation->key] = $violation->name;

                            }

                        }

                    }

                }

            }

            foreach ($y as $key => $tmp) {
                foreach ($x as $j => $com) {
                    $z[$key][$com] = 0;
                }
            }

            foreach ($items as $item) {

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                foreach ($tdDiffs as $tdDiff) {

                    $violations = $tdDiff->violations;
                    if($violations->count()) {

                        foreach ($violations as $violation) {

                            if($violation->added_or_resolved == 'added') {

                                $z[$violation->key][$item->author]++;

                            }

                        }

                    }

                }

            }

            /**
             * Transform to percentages
             * of total violation per committer
             */
            foreach ($z as $key => $array) {
                foreach ($array as $com => $vcount) {
                    if($t[$com] > 0) {
                        $z[$key][$com] = round($vcount/$t[$com]*100, 2);
                    } else {
                        $z[$key][$com] = 0;
                    }
                }
            }

            $z = array_values($z);

            foreach ($z as $j => $array) {
                $z[$j] = array_values($array);
            }


            $resp['w'] = $w;
            $resp['heatMap'][] = [
                'x' => array_values($x_anonymous),
                'y' => array_values($y),
                'z' => array_values($z),
                'type' => 'heatmap',
                'colorscale' => [
                    ['0.0', 'rgb(255, 255, 255)'],
                    ['0.111111111111', 'rgb(206,206,206)'],
                    ['0.222222222222', 'rgb(188,188,188)'],
                    ['0.333333333333', 'rgb(163,163,163)'],
                    ['0.444444444444', 'rgb(135,135,135)'],
                    ['0.555555555556', 'rgb(119, 119, 119)'],
                    ['0.666666666667', 'rgb(104,104,104)'],
                    ['0.777777777778', 'rgb(85,85,85)'],
                    ['0.888888888889', 'rgb(53,53,53)'],
                    ['1.0', 'rgb(0,0,0)']
                ]
            ];

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }

    function rq3(Repo $repo)
    {
        try {

            $resp = [];
            $resp['rq'] = 'RQ3: Do developers add or resolve code violations more often?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('author', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->where('author', '<>', 'n/a')
                ->groupBy('author')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $datapoints_va_count = [];
            $datapoints_vr_count = [];

            $counter = 0;

            foreach ($items as $item) {

                $counter++;

                $commits_count = Commit::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->count();

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $va_count = 0;
                $vr_count = 0;
                foreach ($tdDiffs as $tdDiff) {

                    $violations = $tdDiff->violations;
                    if($violations->count()) {

                        foreach ($violations as $violation) {

                            if($violation->added_or_resolved == 'added') {

                                $va_count ++;

                            } else {

                                $vr_count ++;

                            }

                        }

                    }

                }

                $resp['labels'][] = 'Developer' . $counter;
                $datapoints_va_count[] = $va_count / $commits_count;
                $datapoints_vr_count[] = $vr_count / $commits_count;

            }

            $resp['datasets'][] = [
                'label' => "Violations Added per commit",
                'data' => $datapoints_va_count,
                'backgroundColor' => '#d10e0e',
            ];
            $resp['datasets'][] = [
                'label' => "Violations Resolved per commit",
                'data' => $datapoints_vr_count,
                'backgroundColor' => '#17b529',
            ];

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }

    function rq4(Repo $repo)
    {
        try {

            $resp = [];
            $resp['rq'] = 'RQ4: What is the severity of the violations that developers add?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('author', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->where('author', '<>', 'n/a')
                ->groupBy('author')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $datapoints_iv = [];
            $datapoints_miv = [];
            $datapoints_mav = [];
            $datapoints_cv = [];
            $datapoints_bv = [];

            $counter = 0;

            foreach ($items as $item) {

                $counter++;

                $commits_count = Commit::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->count();

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $datapoints_iv_count = 0;
                $datapoints_miv_count = 0;
                $datapoints_mav_count = 0;
                $datapoints_cv_count = 0;
                $datapoints_bv_count = 0;

                foreach ($tdDiffs as $tdDiff) {

                    $violations = $tdDiff->violations;
                    if($violations->count()) {

                        foreach ($violations as $violation) {

                            if($violation->added_or_resolved == 'added') {

                                switch ($violation->severity) {

                                    case 'INFO':
                                        $datapoints_iv_count ++;
                                        break;
                                    case 'MINOR':
                                        $datapoints_miv_count ++;
                                        break;
                                    case 'MAJOR':
                                        $datapoints_mav_count ++;
                                        break;
                                    case 'CRITICAL':
                                        $datapoints_cv_count ++;
                                        break;
                                    case 'BLOCKER':
                                        $datapoints_bv_count ++;
                                        break;

                                }

                            }

                        }

                    }

                }

                $resp['labels'][] = 'Developer ' . $counter;
                $datapoints_iv[] = $datapoints_iv_count;
                $datapoints_miv[] = $datapoints_miv_count;
                $datapoints_mav[] = $datapoints_mav_count;
                $datapoints_cv[] = $datapoints_cv_count;
                $datapoints_bv[] = $datapoints_bv_count;

            }

            $resp['datasets'][] = [
                'label' => "Info violations",
                'data' => $datapoints_iv,
                'backgroundColor' => '#18b716',
            ];
            $resp['datasets'][] = [
                'label' => "Minor violations",
                'data' => $datapoints_miv,
                'backgroundColor' => '#16b79c',
            ];
            $resp['datasets'][] = [
                'label' => "Major violations",
                'data' => $datapoints_mav,
                'backgroundColor' => '#1646b7',
            ];
            $resp['datasets'][] = [
                'label' => "Critical violations",
                'data' => $datapoints_cv,
                'backgroundColor' => '#b76e16',
            ];
            $resp['datasets'][] = [
                'label' => "Blocker violations",
                'data' => $datapoints_bv,
                'backgroundColor' => '#b71e16',
            ];

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }

    function rq5(Repo $repo)
    {
        try {

            $resp = [];
            $resp['rq'] = 'RQ5: Do developers with more experience on project (older) add less TD?';
            $resp['datasets'][] = [
                'label' => 'Experience vs Added TD',
            ];

            $items = TdDiff::select('author', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->where('author', '<>', 'n/a')
                ->groupBy('author')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $last_commit = Commit::where('repo_id', $repo->id)
                ->latest('committed_at')->first();

            foreach ($items as $item) {

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $author_first_commit = Commit::where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->orderBy('committed_at', 'asc')->first();

                $changed_loc = DB::table('commits')
                    ->join('commit_stats', 'commits.id', '=', 'commit_stats.commit_id')
                    ->where('commits.repo_id', $repo->id)
                    ->where('commits.author', $item->author)
                    ->select(DB::raw('SUM(commit_stats.total) as sum'))
                    ->first()->sum;

                $age_days = $last_commit->committed_at->diffInDays($author_first_commit->committed_at);

                $td_added = TdDiff::select(DB::raw('SUM(sqale_index_diff) as sum'))
                    ->where('repo_id', $repo->id)
                    ->where('author', $item->author)
                    ->first()->sum;

                $resp['datasets'][0]['data'][] = ['x' => $age_days, 'y' => $td_added/$changed_loc, 'r' => 5];
                $resp['datasets'][0]['backgroundColor'] = '#e5690b';
            }

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }
}
