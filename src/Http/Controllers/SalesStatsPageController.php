<?php

namespace Modules\Sirsoft\SalesStats\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;

class SalesStatsPageController extends Controller
{
    public function index()
    {
        $viewPath = dirname(__DIR__, 3).'/resources/views';
        View::addNamespace('sirsoft-sales_stats', $viewPath);

        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        return response()->view('sirsoft-sales_stats::dashboard', [
            'title' => '판매 통계',
            'apiBase' => url('/api/modules/sirsoft-sales_stats'),
        ]);
    }
}
