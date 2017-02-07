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
            $resp['rq'] = 'RQ1: Do developers equally contribute to the accumulation of TD?';
            $resp['labels'] = [];
            $resp['datasets'] = [];

            $items = TdDiff::select('committer', DB::raw('COUNT(*) as count'))
                ->where('repo_id', $repo->id)
                ->groupBy('committer')
                ->orderBy('count', 'DESC')
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

                    /*$violations = $tdDiff->violations;
                    if($violations->count()) {

                        foreach ($violations as $violation) {

                            if($violation->added_or_resolved == 'added') {

                                $v_added_str .= '<tr>' .
                                    '<td valign="top">' . $violation->key . '</td>' .
                                    '<td valign="top">' . $violation->severity . '</td>' .
                                    '<td valign="top"><div><h2><u>' . $violation->name . '</u></h2></div><div>' . $violation->description . '</div></td>' .
                                    '<td valign="top">' . $tdDiff->filename . '</td>' .
                                    '</tr>';

                            } else {

                                $v_resolved_str .= '<tr>' .
                                    '<td valign="top">' . $violation->key . '</td>' .
                                    '<td valign="top">' . $violation->severity . '</td>' .
                                    '<td valign="top"><div><h2><u>' . $violation->name . '</u></h2></div><div>' . $violation->description . '</div></td>' .
                                    '<td valign="top">' . $tdDiff->filename . '</td>' .
                                    '</tr>';

                            }

                        }

                    }*/

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
}
