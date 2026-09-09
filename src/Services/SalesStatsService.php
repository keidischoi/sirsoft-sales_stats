<?php

namespace Modules\Sirsoft\SalesStats\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sirsoft-ecommerce 판매 통계
 *
 * 주의: DB::getTablePrefix() 가 있으면 쿼리빌더 별칭도 prefix 됨
 * (예: o → g7_o). selectRaw / whereRaw 에서는 prefix 된 별칭을 써야 함.
 */
class SalesStatsService
{
    protected array $revenueStatuses = [
        'payment_complete',
        'shipping_hold',
        'preparing',
        'shipping_ready',
        'shipping',
        'delivered',
        'confirmed',
    ];

    /** @return array{o: string, oi: string, pc: string, c: string, prefix: string} */
    protected function aliases(): array
    {
        $p = DB::getTablePrefix() ?: '';

        return [
            'prefix' => $p,
            'o' => $p.'o',
            'oi' => $p.'oi',
            'pc' => $p.'pc',
            'c' => $p.'c',
        ];
    }

    public function summary(array $filters): array
    {
        $this->assertTables();
        $a = $this->aliases();
        $rev = $this->revenueExpression($a['oi']);

        $row = $this->baseQuery($filters)
            ->selectRaw("COUNT(DISTINCT `{$a['o']}`.`id`) as order_count")
            ->selectRaw("COALESCE(SUM(`{$a['oi']}`.`quantity`), 0) as item_qty")
            ->selectRaw("COALESCE(SUM({$rev}), 0) as revenue")
            ->first();

        $statusCounts = $this->orderStatusCounts($filters);

        return [
            'order_count' => (int) ($row->order_count ?? 0),
            'item_qty' => (int) ($row->item_qty ?? 0),
            'revenue' => round((float) ($row->revenue ?? 0), 2),
            // 상태별 주문 수 (주문 테이블 기준, 기간 내)
            'pending_payment' => (int) ($statusCounts['pending_payment'] ?? 0),
            'payment_complete' => (int) ($statusCounts['payment_complete'] ?? 0),
            'preparing' => (int) ($statusCounts['preparing'] ?? 0) + (int) ($statusCounts['shipping_ready'] ?? 0) + (int) ($statusCounts['shipping_hold'] ?? 0),
            'shipping' => (int) ($statusCounts['shipping'] ?? 0),
            'completed' => (int) ($statusCounts['delivered'] ?? 0) + (int) ($statusCounts['confirmed'] ?? 0),
            'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            'status_breakdown' => $statusCounts,
        ];
    }

    /**
     * 기간 내 주문 상태별 건수 (ecommerce_orders 기준)
     *
     * @return array<string, int>
     */
    protected function orderStatusCounts(array $filters): array
    {
        if (! Schema::hasColumn('ecommerce_orders', 'order_status')) {
            return [];
        }

        $from = $filters['from'] ?? '2020-01-01';
        $to = $filters['to'] ?? now()->addDay()->toDateString();
        $a = $this->aliases();
        $dateCol = $this->orderDateExpression($a);

        // 별칭 없이 단건 테이블 조회 (prefix 이슈 최소화)
        $dateParts = [];
        foreach (['ordered_at', 'paid_at', 'created_at'] as $col) {
            if (Schema::hasColumn('ecommerce_orders', $col)) {
                $dateParts[] = '`'.(DB::getTablePrefix() ?: '').'ecommerce_orders`.`'.$col.'`';
            }
        }
        // 단순: 쿼리빌더 whereDate 계열 사용
        $q = DB::table('ecommerce_orders');
        $prefix = DB::getTablePrefix() ?: '';
        $table = $prefix.'ecommerce_orders';

        $coalesceCols = [];
        foreach (['ordered_at', 'paid_at', 'created_at'] as $col) {
            if (Schema::hasColumn('ecommerce_orders', $col)) {
                $coalesceCols[] = "`{$table}`.`{$col}`";
            }
        }
        $dateExpr = count($coalesceCols) > 1
            ? 'COALESCE('.implode(', ', $coalesceCols).')'
            : ($coalesceCols[0] ?? "`{$table}`.`id`");

        $rows = DB::table('ecommerce_orders')
            ->selectRaw('order_status, COUNT(*) as cnt')
            ->whereRaw("DATE({$dateExpr}) >= ?", [$from])
            ->whereRaw("DATE({$dateExpr}) <= ?", [$to])
            ->groupBy('order_status')
            ->pluck('cnt', 'order_status');

        $out = [];
        foreach ($rows as $status => $cnt) {
            $out[(string) $status] = (int) $cnt;
        }

        return $out;
    }

    public function byPeriod(array $filters): array
    {
        $this->assertTables();
        $a = $this->aliases();
        $groupExpr = $this->periodGroupExpression($filters['period'] ?? 'day', $a);
        $rev = $this->revenueExpression($a['oi']);

        $rows = $this->baseQuery($filters)
            ->selectRaw("{$groupExpr} as period_key")
            ->selectRaw("COUNT(DISTINCT `{$a['o']}`.`id`) as order_count")
            ->selectRaw("COALESCE(SUM(`{$a['oi']}`.`quantity`), 0) as item_qty")
            ->selectRaw("COALESCE(SUM({$rev}), 0) as revenue")
            ->groupBy(DB::raw($groupExpr))
            ->orderBy(DB::raw($groupExpr))
            ->get();

        return $rows->map(fn ($r) => [
            'period' => (string) $r->period_key,
            'order_count' => (int) $r->order_count,
            'item_qty' => (int) $r->item_qty,
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    public function byProduct(array $filters, int $limit = 50): array
    {
        $this->assertTables();
        $a = $this->aliases();
        $rev = $this->revenueExpression($a['oi']);
        $nameSel = Schema::hasColumn('ecommerce_order_options', 'product_name')
            ? "MAX(`{$a['oi']}`.`product_name`)"
            : "MAX(`{$a['oi']}`.`product_id`)";

        $rows = $this->baseQuery($filters)
            ->selectRaw("`{$a['oi']}`.`product_id` as product_id")
            ->selectRaw("{$nameSel} as product_name_raw")
            ->selectRaw("COALESCE(SUM(`{$a['oi']}`.`quantity`), 0) as item_qty")
            ->selectRaw("COALESCE(SUM({$rev}), 0) as revenue")
            ->selectRaw("COUNT(DISTINCT `{$a['o']}`.`id`) as order_count")
            ->groupBy(DB::raw("`{$a['oi']}`.`product_id`"))
            ->orderByDesc(DB::raw('revenue'))
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'product_name' => $this->extractLocaleName($r->product_name_raw ?? null),
            'order_count' => (int) $r->order_count,
            'item_qty' => (int) $r->item_qty,
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    public function byCategory(array $filters, int $limit = 50): array
    {
        $this->assertTables();

        if (! Schema::hasTable('ecommerce_product_categories') || ! Schema::hasTable('ecommerce_categories')) {
            return [];
        }

        $a = $this->aliases();
        $rev = $this->revenueExpression($a['oi']);

        $rows = $this->baseQuery($filters)
            ->leftJoin('ecommerce_product_categories as pc', 'pc.product_id', '=', 'oi.product_id')
            ->leftJoin('ecommerce_categories as c', 'c.id', '=', 'pc.category_id')
            ->selectRaw("COALESCE(`{$a['c']}`.`id`, 0) as category_id")
            ->selectRaw("MAX(`{$a['c']}`.`name`) as category_name_raw")
            ->selectRaw("COALESCE(SUM(`{$a['oi']}`.`quantity`), 0) as item_qty")
            ->selectRaw("COALESCE(SUM({$rev}), 0) as revenue")
            ->selectRaw("COUNT(DISTINCT `{$a['o']}`.`id`) as order_count")
            ->groupBy(DB::raw("COALESCE(`{$a['c']}`.`id`, 0)"))
            ->orderByDesc(DB::raw('revenue'))
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'category_id' => (int) $r->category_id,
            'category_name' => ((int) $r->category_id)
                ? $this->extractLocaleName($r->category_name_raw ?? null)
                : '미분류',
            'order_count' => (int) $r->order_count,
            'item_qty' => (int) $r->item_qty,
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();
    }

    protected function revenueExpression(string $oiAlias): string
    {
        $hasPaid = Schema::hasColumn('ecommerce_order_options', 'subtotal_paid_amount');
        $hasPrice = Schema::hasColumn('ecommerce_order_options', 'subtotal_price');
        $hasUnit = Schema::hasColumn('ecommerce_order_options', 'unit_price');

        if ($hasPaid && $hasPrice) {
            return "COALESCE(`{$oiAlias}`.`subtotal_paid_amount`, `{$oiAlias}`.`subtotal_price`, 0)";
        }
        if ($hasPaid) {
            return "COALESCE(`{$oiAlias}`.`subtotal_paid_amount`, 0)";
        }
        if ($hasPrice) {
            return "COALESCE(`{$oiAlias}`.`subtotal_price`, 0)";
        }
        if ($hasUnit) {
            return "COALESCE(`{$oiAlias}`.`unit_price`, 0) * COALESCE(`{$oiAlias}`.`quantity`, 0)";
        }

        return '0';
    }

    protected function baseQuery(array $filters)
    {
        $from = $filters['from'] ?? '2020-01-01';
        $to = $filters['to'] ?? now()->addDay()->toDateString();
        $a = $this->aliases();
        $dateCol = $this->orderDateExpression($a);

        $q = DB::table('ecommerce_order_options as oi')
            ->join('ecommerce_orders as o', 'o.id', '=', 'oi.order_id');

        // where 절은 쿼리빌더가 별칭에 prefix 적용
        if (Schema::hasColumn('ecommerce_orders', 'order_status')) {
            if (! empty($filters['strict_status'])) {
                $q->whereIn('o.order_status', $this->revenueStatuses);
            } else {
                $q->whereNotIn('o.order_status', ['cancelled', 'pending_order']);
            }
        }

        if (Schema::hasColumn('ecommerce_order_options', 'option_status') && ! empty($filters['strict_status'])) {
            $q->where(function ($w) {
                $w->whereIn('oi.option_status', $this->revenueStatuses)
                    ->orWhereNull('oi.option_status');
            });
        }

        // whereRaw 는 prefix 된 별칭 필요
        $q->whereRaw("DATE({$dateCol}) >= ?", [$from])
            ->whereRaw("DATE({$dateCol}) <= ?", [$to]);

        return $q;
    }

    protected function orderDateExpression(array $a): string
    {
        $parts = [];
        foreach (['ordered_at', 'paid_at', 'created_at'] as $col) {
            if (Schema::hasColumn('ecommerce_orders', $col)) {
                $parts[] = "`{$a['o']}`.`{$col}`";
            }
        }
        if (count($parts) === 0) {
            return "`{$a['o']}`.`id`";
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        return 'COALESCE('.implode(', ', $parts).')';
    }

    protected function periodGroupExpression(string $period, array $a): string
    {
        $date = $this->orderDateExpression($a);
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'postgres'], true)) {
            return match ($period) {
                'month' => "to_char({$date}, 'YYYY-MM')",
                'year' => "to_char({$date}, 'YYYY')",
                default => "to_char({$date}, 'YYYY-MM-DD')",
            };
        }

        if ($driver === 'sqlite') {
            return match ($period) {
                'month' => "strftime('%Y-%m', {$date})",
                'year' => "strftime('%Y', {$date})",
                default => "strftime('%Y-%m-%d', {$date})",
            };
        }

        return match ($period) {
            'month' => "DATE_FORMAT({$date}, '%Y-%m')",
            'year' => "DATE_FORMAT({$date}, '%Y')",
            default => "DATE({$date})",
        };
    }

    protected function extractLocaleName(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '(이름 없음)';
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $v = $decoded['ko'] ?? $decoded['en'] ?? reset($decoded);

            return ($v !== false && $v !== null && $v !== '') ? (string) $v : '(이름 없음)';
        }

        return (string) $raw;
    }


    /**
     * 이커머스 모듈 기본 통화 정보
     *
     * @return array{code: string, symbol: string, decimal_places: int, prefix: string, suffix: string}
     */
    public function currencyInfo(): array
    {
        $code = 'KRW';
        $decimalPlaces = 0;
        $symbol = '₩';
        $prefix = '₩';
        $suffix = '';

        try {
            if (function_exists('g7_module_settings')) {
                $settings = g7_module_settings('sirsoft-ecommerce', 'language_currency') ?: [];
                $code = (string) ($settings['default_currency'] ?? 'KRW');
                $currencies = $settings['currencies'] ?? [];
                foreach ($currencies as $c) {
                    if (($c['code'] ?? '') === $code) {
                        $decimalPlaces = (int) ($c['decimal_places'] ?? ($code === 'KRW' || $code === 'JPY' ? 0 : 2));
                        $symbol = (string) ($c['symbol'] ?? $symbol);
                        break;
                    }
                }
            }

            // CurrencyConversionService 가 있으면 format 규칙 활용
            if (class_exists(\Modules\Sirsoft\Ecommerce\Services\CurrencyConversionService::class)) {
                /** @var \Modules\Sirsoft\Ecommerce\Services\CurrencyConversionService $svc */
                $svc = app(\Modules\Sirsoft\Ecommerce\Services\CurrencyConversionService::class);
                $code = $svc->getDefaultCurrency();
                $decimalPlaces = $svc->getDecimalPlaces($code);
            }
        } catch (\Throwable $e) {
            // 폴백 유지
        }

        // 흔한 통화 기호
        $symbols = [
            'KRW' => '₩', 'USD' => '$', 'EUR' => '€', 'JPY' => '¥',
            'CNY' => '¥', 'GBP' => '£', 'VND' => '₫', 'THB' => '฿',
        ];
        $symbol = $symbols[$code] ?? ($symbol ?: $code);

        // KRW/JPY 는 보통 접두 기호, 그 외는 코드 접미도 허용
        if (in_array($code, ['KRW', 'JPY', 'USD', 'EUR', 'GBP', 'CNY'], true)) {
            $prefix = $symbol;
            $suffix = '';
        } else {
            $prefix = '';
            $suffix = ' '.$code;
        }

        if ($code === 'KRW' || $code === 'JPY') {
            $decimalPlaces = 0;
        }

        return [
            'code' => $code,
            'symbol' => $symbol,
            'decimal_places' => $decimalPlaces,
            'prefix' => $prefix,
            'suffix' => $suffix,
        ];
    }

    public function formatMoney(float|int $amount): string
    {
        $c = $this->currencyInfo();

        try {
            if (class_exists(\Modules\Sirsoft\Ecommerce\Services\CurrencyConversionService::class)) {
                $svc = app(\Modules\Sirsoft\Ecommerce\Services\CurrencyConversionService::class);

                return $svc->formatPrice($amount, $c['code']);
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $formatted = number_format((float) $amount, (int) $c['decimal_places']);

        return $c['prefix'].$formatted.$c['suffix'];
    }

    protected function assertTables(): void
    {
        if (! Schema::hasTable('ecommerce_orders')) {
            throw new \RuntimeException('테이블 없음: ecommerce_orders');
        }
        if (! Schema::hasTable('ecommerce_order_options')) {
            throw new \RuntimeException('테이블 없음: ecommerce_order_options');
        }
    }
}
