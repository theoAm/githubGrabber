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

        echo "<table cellpadding='5'>
        <thead>
        <tr>
            <th style='border-bottom: 1px solid black;'>Committer</th>        
            <th style='border-bottom: 1px solid black;'>#commits</th>        
            <th style='border-bottom: 1px solid black;'>Sqale_Index_Added</th>        
            <th style='border-bottom: 1px solid black;'>Violations_Added</th>
            <th style='border-bottom: 1px solid black;'>Violations_Resolved</th>
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
            $v_added_str = '<table><tr><th>Key</th><th>Severity</th><th>Name & Description</th><th>File</th></tr>';
            $v_resolved_str = '<table><tr><th>Key</th><th>Severity</th><th>Name & Description</th><th>File</th></tr>';
            foreach ($tdDiffs as $tdDiff) {

                $sqale_index_diff += $tdDiff->sqale_index_diff;

                $violations = $tdDiff->violations;
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

                }

            }
            $v_added_str .= '</table>';
            $v_resolved_str .= '</table>';

            echo "<td valign='top' style='width: 200px; height: 30px; border-bottom: 1px solid grey;'>{$committer}</td>";
            echo "<td valign='top' style='width: 100px; height: 30px; border-bottom: 1px solid grey;'>{$commits}</td>";
            echo "<td valign='top' style='width: 100px; height: 30px; border-bottom: 1px solid grey;'>{$sqale_index_diff} min</td>";
            echo "<td valign='top' style='width:500px; border-bottom: 1px solid grey;'>{$v_added_str}</td>";
            echo "<td valign='top' style='width:500px; border-bottom: 1px solid grey;'>{$v_resolved_str}</td>";

            echo "</tr>";

        }

        echo "</tbody>";
        echo "</table>";

    }
}
