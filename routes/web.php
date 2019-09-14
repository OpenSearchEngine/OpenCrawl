<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (\Illuminate\Http\Request $request) {
    $validator = validator($request->all(), [
        'url' => 'required|filled|url|max:250'
    ]);
    if(!$validator->fails()){
        \App\Jobs\CrawlPage::dispatch($request->get('url'));
    }
});
Route::get('/test', function(){
    $job = new \App\Jobs\CrawlPage('https://cnn.com');
    $job->testMode = true;
    $data = $job->handle();

    die(var_dump($data));
});