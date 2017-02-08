<?php

namespace App\Http\Controllers;

use App\Commit;
use App\Repo;
use App\TdDiff;
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
            $resp['rq'] = 'RQ1: Developers equally harm TD?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('committer', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->groupBy('committer')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $datapoints = [];

            foreach ($items as $item) {

                $commits_count = Commit::where('repo_id', $repo->id)
                    ->where('author', $item->committer)
                    ->count();

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('committer', $item->committer)
                    ->get();

                if(!$tdDiffs->count()) {
                    continue;
                }

                $sqale_index_diff = 0;
                foreach ($tdDiffs as $tdDiff) {

                    $sqale_index_diff += $tdDiff->sqale_index_diff;

                }

                $resp['labels'][] = $item->committer;
                $datapoints[] = $sqale_index_diff / $commits_count;

            }

            $resp['datasets'][] = [
                'label' => "TD added per commit (in minutes)",
                'data' => $datapoints
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
            $resp['rq'] = 'RQ3: Developers add or resolve code violations more often?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('committer', DB::raw('SUM(sqale_index_diff) as sum'))
                ->where('repo_id', $repo->id)
                ->groupBy('committer')
                ->orderBy('sum', 'DESC')
                ->get();

            if(!$items->count()) {
                throw new \Exception();
            }

            $datapoints_va_count = [];
            $datapoints_vr_count = [];

            foreach ($items as $item) {

                $commits_count = Commit::where('repo_id', $repo->id)
                    ->where('author', $item->committer)
                    ->count();

                $tdDiffs = TdDiff::where('repo_id', $repo->id)
                    ->where('committer', $item->committer)
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

                $resp['labels'][] = $item->committer;
                $datapoints_va_count[] = $va_count / $commits_count;
                $datapoints_vr_count[] = $vr_count / $commits_count;

            }

            $resp['datasets'][] = [
                'label' => "Violations Added per commit",
                'data' => $datapoints_va_count
            ];
            $resp['datasets'][] = [
                'label' => "Violations Resolved per commit",
                'data' => $datapoints_vr_count
            ];

            return $resp;

        } catch (\Exception $ex) {
            exit;
        }
    }
}
