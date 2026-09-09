<?php

namespace Modules\Sirsoft\SalesStats\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Sirsoft\SalesStats\Services\SalesStatsService;

class SalesStatsController extends Controller
{
    protected function service(): SalesStatsService
    {
        $f = dirname(__DIR__, 3).'/Services/SalesStatsService.php';
        if (! class_exists(SalesStatsService::class, false) && is_file($f)) {
            require_once $f;
        }

        return new SalesStatsService;
    }

    public function index(Request $request)
    {
        $svc = $this->service();
        $currency = $svc->currencyInfo();

        $empty = [
            'currency' => $currency,
            'summary' => [
                'revenue' => 0,
                'revenue_formatted' => $svc->formatMoney(0),
                'order_count' => 0,
                'item_qty' => 0,
                'pending_payment' => 0,
                'shipping' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'preparing' => 0,
            ],
            'by_period' => [],
            'by_product' => [],
            'by_category' => [],
        ];

        try {
            $filters = $this->filters($request);

            $summary = $svc->summary($filters);
            $summary['revenue_formatted'] = $svc->formatMoney($summary['revenue'] ?? 0);

            $byPeriod = array_map(function ($r) use ($svc) {
                $r['revenue_formatted'] = $svc->formatMoney($r['revenue'] ?? 0);

                return $r;
            }, $svc->byPeriod($filters));

            $byProduct = array_map(function ($r) use ($svc) {
                $r['revenue_formatted'] = $svc->formatMoney($r['revenue'] ?? 0);

                return $r;
            }, $svc->byProduct($filters, (int) $request->input('product_limit', 30)));

            $byCategory = array_map(function ($r) use ($svc) {
                $r['revenue_formatted'] = $svc->formatMoney($r['revenue'] ?? 0);

                return $r;
            }, $svc->byCategory($filters, (int) $request->input('category_limit', 20)));

            $data = [
                'currency' => $currency,
                'filters' => $filters,
                'summary' => $summary,
                'by_period' => $byPeriod,
                'by_product' => $byProduct,
                'by_category' => $byCategory,
            ];

            return response()->json([
                'success' => true,
                'message' => 'ok',
                'data' => $data,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Log::error('[sirsoft-sales_stats] '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => array_merge($empty, [
                    'error' => $e->getMessage(),
                    'exception' => class_basename($e),
                ]),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function export(Request $request)
    {
        try {
            $filters = $this->filters($request);
            $type = $request->input('type', 'product');
            $svc = $this->service();
            $currency = $svc->currencyInfo();
            $rows = match ($type) {
                'category' => $svc->byCategory($filters, 500),
                'period' => $svc->byPeriod($filters),
                default => $svc->byProduct($filters, 500),
            };

            $filename = 'sales_stats_'.$type.'_'.date('Ymd_His').'.csv';

            return response()->streamDownload(function () use ($rows, $type, $currency, $svc) {
                $h = fopen('php://output', 'w');
                fprintf($h, chr(0xEF).chr(0xBB).chr(0xBF));
                $revHeader = '매출액('.$currency['code'].')';
                if ($type === 'period') {
                    fputcsv($h, ['기간', '주문수', '판매수량', $revHeader, '매출(표시)']);
                    foreach ($rows as $r) {
                        fputcsv($h, [$r['period'], $r['order_count'], $r['item_qty'], $r['revenue'], $svc->formatMoney($r['revenue'])]);
                    }
                } elseif ($type === 'category') {
                    fputcsv($h, ['카테고리ID', '카테고리명', '주문수', '판매수량', $revHeader, '매출(표시)']);
                    foreach ($rows as $r) {
                        fputcsv($h, [$r['category_id'], $r['category_name'], $r['order_count'], $r['item_qty'], $r['revenue'], $svc->formatMoney($r['revenue'])]);
                    }
                } else {
                    fputcsv($h, ['상품ID', '상품명', '주문수', '판매수량', $revHeader, '매출(표시)']);
                    foreach ($rows as $r) {
                        fputcsv($h, [$r['product_id'], $r['product_name'], $r['order_count'], $r['item_qty'], $r['revenue'], $svc->formatMoney($r['revenue'])]);
                    }
                }
                fclose($h);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function filters(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'period' => 'nullable|in:day,month,year',
            'strict_status' => 'nullable|boolean',
        ])->validate();

        // 빈 문자열은 미지정으로 처리
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        if ($from === null || $from === '') {
            $from = now()->subDays(29)->toDateString(); // 기본 30일
        }
        if ($to === null || $to === '') {
            $to = now()->toDateString();
        }

        $data['from'] = $from;
        $data['to'] = $to;
        $data['period'] = $data['period'] ?? 'day';
        $data['strict_status'] = (bool) ($data['strict_status'] ?? false);

        return $data;
    }
}
