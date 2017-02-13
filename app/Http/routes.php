<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/home', 'HomeController@home');

Route::get('/', 'ResultsController@index');
Route::get('/results/td/{repo}', 'ResultsController@td');
Route::get('/results/rq1/{repo}', 'ResultsController@rq1');
Route::get('/results/rq2/{repo}', 'ResultsController@rq2');
Route::get('/results/rq3/{repo}', 'ResultsController@rq3');
Route::get('/results/rq4/{repo}', 'ResultsController@rq4');
Route::get('/results/rq5/{repo}', 'ResultsController@rq5');

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web']], function () {
    //
});
