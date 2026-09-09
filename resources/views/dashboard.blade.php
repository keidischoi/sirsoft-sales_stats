<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? '판매 통계' }} · 그누보드7</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; }
        .card { @apply bg-white rounded-2xl border border-slate-200 shadow-sm; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6" id="app">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight">판매 통계</h1>
            <p class="text-slate-500 mt-1 text-sm">일별 · 월별 · 연간 × 상품별 · 카테고리별</p>
        </div>
        <div class="flex gap-2">
            <button onclick="exportCsv()" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-sm font-medium shadow-sm hover:bg-slate-50">
                CSV 내보내기
            </button>
            <button onclick="loadData()" id="btnRefresh" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium shadow-sm hover:bg-indigo-500">
                새로고침
            </button>
            <a href="/admin" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-medium">관리자 홈</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-end gap-4 flex-wrap">
            <div class="flex gap-2">
                <button data-period="day"   class="period-btn px-4 py-2 rounded-xl text-sm font-medium bg-indigo-600 text-white">일별</button>
                <button data-period="month" class="period-btn px-4 py-2 rounded-xl text-sm font-medium bg-slate-100 text-slate-700">월별</button>
                <button data-period="year"  class="period-btn px-4 py-2 rounded-xl text-sm font-medium bg-slate-100 text-slate-700">연간</button>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">시작일</label>
                    <input type="date" id="from" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">종료일</label>
                    <input type="date" id="to" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <button onclick="loadData()" class="mt-5 px-5 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold">조회</button>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        <div class="relative overflow-hidden bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 opacity-10"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-slate-500">총 매출</p>
                    <p class="mt-2 text-xl md:text-2xl font-bold text-slate-900" id="sumRevenue">—</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-2xl text-indigo-600 shadow-inner">💰</div>
            </div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 opacity-10"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-slate-500">주문 수</p>
                    <p class="mt-2 text-xl md:text-2xl font-bold text-slate-900" id="sumOrders">—</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-2xl text-emerald-600 shadow-inner">🧾</div>
            </div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-gradient-to-br from-amber-500 to-orange-500 opacity-10"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-slate-500">판매 수량</p>
                    <p class="mt-2 text-xl md:text-2xl font-bold text-slate-900" id="sumQty">—</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-2xl text-amber-600 shadow-inner">📦</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 bg-slate-100 rounded-xl w-fit">
        <button data-tab="period"   class="tab-btn px-4 py-2 rounded-lg text-sm font-medium bg-white shadow text-slate-900">기간 추이</button>
        <button data-tab="product"  class="tab-btn px-4 py-2 rounded-lg text-sm font-medium text-slate-600">상품별</button>
        <button data-tab="category" class="tab-btn px-4 py-2 rounded-lg text-sm font-medium text-slate-600">카테고리별</button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        <div class="xl:col-span-3 bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-lg font-semibold mb-4" id="chartTitle">기간 매출 추이</h2>
            <div class="h-80 relative">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
        <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 shadow-sm overflow-hidden">
            <h2 class="text-lg font-semibold mb-4">상세 데이터</h2>
            <div class="overflow-auto max-h-80">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white">
                        <tr class="text-left text-slate-500 border-b">
                            <th class="pb-2 font-medium" id="colName">기간</th>
                            <th class="pb-2 font-medium text-right">주문</th>
                            <th class="pb-2 font-medium text-right">수량</th>
                            <th class="pb-2 font-medium text-right">매출</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="4" class="py-8 text-center text-slate-400">불러오는 중…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 pt-4">
        ※ 테이블명이 실제 스키마와 다르면 <code>SalesStatsService.php</code>를 수정하세요.
    </p>
</div>

<script>
const API = @json($apiBase);
let period = 'day';
let activeTab = 'period';
let chart = null;
let cache = { by_period: [], by_product: [], by_category: [], summary: null };

function money(v) {
    return new Intl.NumberFormat('ko-KR', { style: 'currency', currency: 'KRW', maximumFractionDigits: 0 }).format(v || 0);
}
function num(v) {
    return new Intl.NumberFormat('ko-KR').format(v || 0);
}

// 기본 날짜: 최근 30일
(function initDates() {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 30);
    document.getElementById('to').value = to.toISOString().slice(0, 10);
    document.getElementById('from').value = from.toISOString().slice(0, 10);
})();

document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        period = btn.dataset.period;
        document.querySelectorAll('.period-btn').forEach(b => {
            b.className = 'period-btn px-4 py-2 rounded-xl text-sm font-medium ' +
                (b.dataset.period === period ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700');
        });
        loadData();
    });
});

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        activeTab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.className = 'tab-btn px-4 py-2 rounded-lg text-sm font-medium ' +
                (b.dataset.tab === activeTab ? 'bg-white shadow text-slate-900' : 'text-slate-600');
        });
        render();
    });
});

async function loadData() {
    const btn = document.getElementById('btnRefresh');
    btn.textContent = '불러오는 중…';
    btn.disabled = true;
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    try {
        const res = await fetch(`${API}?from=${from}&to=${to}&period=${period}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error((json && json.message) ? json.message : ('HTTP ' + res.status));
        if (json.success) {
            cache = json.data;
            document.getElementById('sumRevenue').textContent = money(cache.summary?.revenue);
            document.getElementById('sumOrders').textContent = num(cache.summary?.order_count);
            document.getElementById('sumQty').textContent = num(cache.summary?.item_qty);
            render();
        } else {
            alert(json.message || '데이터를 불러오지 못했습니다.');
        }
    } catch (e) {
        console.error(e);
        document.getElementById('tableBody').innerHTML =
            `<tr><td colspan="4" class="py-8 text-center text-red-500">API 오류: ${e.message}<br><span class="text-xs text-slate-400">테이블명/권한/라우트를 확인하세요.</span></td></tr>`;
    } finally {
        btn.textContent = '새로고침';
        btn.disabled = false;
    }
}

function render() {
    let rows = [];
    let labels = [];
    let revenues = [];
    let title = '';
    let col = '';

    if (activeTab === 'period') {
        rows = cache.by_period || [];
        title = (period === 'day' ? '일별' : period === 'month' ? '월별' : '연간') + ' 매출 추이';
        col = '기간';
        labels = rows.map(r => r.period);
        revenues = rows.map(r => r.revenue);
    } else if (activeTab === 'product') {
        rows = cache.by_product || [];
        title = '상품별 매출 (상위)';
        col = '상품';
        labels = rows.slice(0, 12).map(r => (r.product_name || '').slice(0, 12));
        revenues = rows.slice(0, 12).map(r => r.revenue);
    } else {
        rows = cache.by_category || [];
        title = '카테고리별 매출';
        col = '카테고리';
        labels = rows.map(r => r.category_name);
        revenues = rows.map(r => r.revenue);
    }

    document.getElementById('chartTitle').textContent = title;
    document.getElementById('colName').textContent = col;

    const tbody = document.getElementById('tableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-400">데이터가 없습니다</td></tr>';
    } else {
        tbody.innerHTML = rows.map(r => {
            const name = r.period || r.product_name || r.category_name || '-';
            return `<tr class="border-b border-slate-50 hover:bg-slate-50">
                <td class="py-2.5 pr-2 truncate max-w-[140px]" title="${name}">${name}</td>
                <td class="py-2.5 text-right tabular-nums">${num(r.order_count)}</td>
                <td class="py-2.5 text-right tabular-nums">${num(r.item_qty)}</td>
                <td class="py-2.5 text-right tabular-nums font-medium text-indigo-600">${money(r.revenue)}</td>
            </tr>`;
        }).join('');
    }

    // Chart
    const ctx = document.getElementById('mainChart');
    if (chart) chart.destroy();
    const isPie = activeTab === 'category';
    chart = new Chart(ctx, {
        type: isPie ? 'doughnut' : (activeTab === 'product' ? 'bar' : 'line'),
        data: {
            labels,
            datasets: [{
                label: '매출',
                data: revenues,
                backgroundColor: isPie
                    ? ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#a855f7','#ec4899','#14b8a6']
                    : (activeTab === 'product' ? '#6366f1' : 'rgba(99,102,241,0.15)'),
                borderColor: '#6366f1',
                borderWidth: 2,
                tension: 0.3,
                fill: activeTab === 'period',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: isPie },
                tooltip: {
                    callbacks: {
                        label: (c) => money(c.raw)
                    }
                }
            },
            indexAxis: activeTab === 'product' ? 'y' : 'x',
            scales: isPie ? {} : {
                y: { ticks: { callback: v => v >= 10000 ? (v/10000)+'만' : v } }
            }
        }
    });
}

function exportCsv() {
    const from = document.getElementById('from').value;
    const to = document.getElementById('to').value;
    const type = activeTab === 'period' ? 'period' : activeTab === 'product' ? 'product' : 'category';
    window.open(`${API}/export?from=${from}&to=${to}&period=${period}&type=${type}`, '_blank');
}

// 초기 로드
loadData();
</script>
</body>
</html>
