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
        $items = TdDiff::select('committer', DB::raw('COUNT(*) as count'))
            ->where('repo_id', $repo->id)
            ->groupBy('committer')
            ->orderBy('count', 'DESC')
            ->get();
        if(!$items->count()) {
            echo 'No committers found!';
            exit;
        }

        echo "<p><a href='/'>back</a></p>";

        echo "<h1>{$repo->owner}/{$repo->name}</h1>";

        echo "<table>
        <thead>
        <tr>
            <th style='border-bottom: 1px solid black;'>Committer</th>        
            <th style='border-bottom: 1px solid black;'>#commits</th>        
            <th style='border-bottom: 1px solid black;'>Sqale_Index_Added</th>        
            <th style='border-bottom: 1px solid black;'>Violations_Added (distinct)</th>        
            <th style='border-bottom: 1px solid black;'>Violations_Resolved (distinct)</th>        
        </tr>
        </thead>
        <tbody>";

        foreach ($items as $item) {

            echo "<tr>";

            $committer = $item->committer;
            $tdDiffs = TdDiff::where('repo_id', $repo->id)
                ->where('committer', $committer)
                ->get();
            if(!$tdDiffs->count()) {
                continue;
            }

            $commits = Commit::where('repo_id', $repo->id)
                ->where('committer', $committer)
                ->count();

            $sqale_index_diff = 0;
            $v_added_str = '';
            $v_resolved_str = '';
            foreach ($tdDiffs as $tdDiff) {

                $sqale_index_diff += $tdDiff->sqale_index_diff;

                $violations = $tdDiff->violations;
                if($violations->count()) {

                    foreach ($violations as $violation) {

                        if($violation->added_or_resolved == 'added') {
                            /**
                             * EDOOOOOO
                             */
                        } else {

                        }

                    }

                }

            }

            echo "<td valign='top' style='width: 200px; height: 30px; border-bottom: 1px solid grey; padding: 5px;'>{$committer}</td>";
            echo "<td valign='top' style='width: 100px; height: 30px; border-bottom: 1px solid grey; padding: 5px;'>{$commits}</td>";
            echo "<td valign='top' style='width: 100px; height: 30px; border-bottom: 1px solid grey; padding: 5px;'>{$sqale_index_diff} min</td>";
            echo "<td valign='top' style='width: 500px; height: 30px; border-bottom: 1px solid grey; padding: 5px;'>{$v_added_str}</td>";
            echo "<td valign='top' style='width: 500px; height: 30px; border-bottom: 1px solid grey; padding: 5px;'>{$v_resolved_str}</td>";

            echo "</tr>";

        }

        echo "</tbody>";
        echo "</table>";

    }
}
