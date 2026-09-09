<?php

use Illuminate\Support\Facades\Route;

$base = dirname(__DIR__);

if (! class_exists(\Modules\Sirsoft\SalesStats\Http\Controllers\SalesStatsPageController::class, false)) {
    require_once $base.'/Http/Controllers/SalesStatsPageController.php';
}

Route::get('/sales-stats', [\Modules\Sirsoft\SalesStats\Http\Controllers\SalesStatsPageController::class, 'index'])
    ->name('sales-stats');
