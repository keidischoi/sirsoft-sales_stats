<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$base = dirname(__DIR__);

foreach ([
    $base.'/Services/SalesStatsService.php',
    $base.'/Http/Controllers/Admin/SalesStatsController.php',
] as $f) {
    if (is_file($f)) {
        require_once $f;
    }
}

/*
| prefix: api/modules/sirsoft-sales_stats  (ModuleRouteServiceProvider)
*/

// 진단용 (로그인만 필요) — 문제 파악용
Route::middleware(['auth:sanctum'])->get('/ping', function () {
    $info = [
        'ok' => true,
        'php' => PHP_VERSION,
        'user' => auth()->id(),
        'tables' => [
            'ecommerce_orders' => Schema::hasTable('ecommerce_orders'),
            'ecommerce_order_options' => Schema::hasTable('ecommerce_order_options'),
            'ecommerce_products' => Schema::hasTable('ecommerce_products'),
            'ecommerce_categories' => Schema::hasTable('ecommerce_categories'),
            'ecommerce_product_categories' => Schema::hasTable('ecommerce_product_categories'),
        ],
    ];

    try {
        if ($info['tables']['ecommerce_orders']) {
            $info['orders_count'] = DB::table('ecommerce_orders')->count();
            $info['order_statuses'] = DB::table('ecommerce_orders')
                ->select('order_status', DB::raw('count(*) as c'))
                ->groupBy('order_status')
                ->pluck('c', 'order_status');
        }
        if ($info['tables']['ecommerce_order_options']) {
            $info['options_count'] = DB::table('ecommerce_order_options')->count();
            $cols = ['id', 'order_id', 'product_id', 'quantity', 'subtotal_price', 'subtotal_paid_amount', 'option_status', 'product_name'];
            $info['option_columns'] = [];
            foreach ($cols as $c) {
                $info['option_columns'][$c] = Schema::hasColumn('ecommerce_order_options', $c);
            }
        }
    } catch (\Throwable $e) {
        $info['db_error'] = $e->getMessage();
    }

    return response()->json(['success' => true, 'data' => $info], 200, [], JSON_UNESCAPED_UNICODE);
})->name('ping');

// 메인 통계 — sanctum만 (admin 미들웨어는 환경에 따라 403)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [\Modules\Sirsoft\SalesStats\Http\Controllers\Admin\SalesStatsController::class, 'index'])
        ->name('index');
    Route::get('/export', [\Modules\Sirsoft\SalesStats\Http\Controllers\Admin\SalesStatsController::class, 'export'])
        ->name('export');
});
