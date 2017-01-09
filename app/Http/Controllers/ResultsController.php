<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class ResultsController extends Controller
{
    function index()
    {
        $path = app_path() . '/Logs';
        if (!is_dir($path)){
            return 'No results found!';
        }

        $tmp = array();

        $dh = opendir($path);
        if ($dh){
            while($folder = readdir($dh)) {
                if($folder != '.' && $folder != '..') {
                    if(is_dir($path . '/' . $folder)) {
                        $r = opendir($path . '/' . $folder);
                        if($r) {
                            while($f = readdir($r)) {
                                if($f != '.' && $f != '..') {
                                    $tmp[$folder][] = $f;
                                }
                            }
                            closedir($r);
                        }
                    }
                }
            }
            closedir($dh);
        }

        if(!$tmp) {
            return "No results found!";
        }

        $results = array();

        foreach ($tmp as $repo => $files) {

            if(!in_array('commitsTD.log', $files)) {
                continue;
            }
            $results[] = $repo;

        }

        if(!$results) {
            return "No results found!";
        }

        asort($results);

        echo "<p><u>Repositories:</u></p>";
        foreach ($results as $repo) {

            echo "<div><a href='/results/commitstd/{$repo}'>{$repo}</a></div>";

        }


    }

    function commitstd($repo)
    {
        $path = app_path() . '/Logs/' . $repo . '/commitsTD.log';
        if(!file_exists($path)) {
            return "No results found!";
        }

        $content = file_get_contents($path);
        $rows = explode("\n", $content);
        foreach ($rows as $row) {

            if($row) {
                $tmp = explode(',', $row);
                echo '<pre>';
                print_r($tmp);
                echo '</pre>';
                exit;
            }

        }

    }
}
