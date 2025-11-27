<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SoapServerController;

Route::get('/', function () {
    return view('welcome');
});

// Routes SOAP
Route::get('/soap/client.wsdl', [SoapServerController::class, 'wsdl'])->name('soap.wsdl');
Route::post('/soap/server', [SoapServerController::class, 'handle'])->name('soap.server');
Route::get('/soap/server', [SoapServerController::class, 'handle'])->name('soap.server.get');
