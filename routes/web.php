<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

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

Route::get('/', function () {
    return view('welcome');
});


Route::get('migrate', function(){
    try {
        Artisan::call('migrate');
        $output = Artisan::output();
        echo "Migrations successfully executed:\n$output";
    } catch (\Exception $e) {
        // Handle any exceptions
        echo "Failed to execute migrations: " . $e->getMessage();
    }
});