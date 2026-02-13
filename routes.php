<?php declare(strict_types=1);

use RatMD\Laika\Http\Controller as Controller;

/**
 * Register CMS routes before all user routes.
 */
    Route::post('/x-laika/filter', [Controller\LaikaController::class, 'filterContent']);
