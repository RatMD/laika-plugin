<?php declare(strict_types=1);

use RatMD\Laika\Http\Controller as Controller;

Route::post('/x-laika/filter', [Controller\LaikaController::class, 'filterContent']);
Route::get('/x-laika/resolve', [Controller\LaikaController::class, 'resolveLink']);
