# sirsoft-sales_stats v1.2.0

## 관리자 SPA 연동 (필수)

| 파일 | 역할 |
|------|------|
| `resources/routes/admin.json` | SPA 경로 `*/admin/ecommerce/sales-stats` |
| `resources/layouts/admin/admin_sales_stats.json` | `_admin_base` 상속 (왼쪽 메뉴 유지) |
| `src/routes/api.php` | `/api/modules/sirsoft-sales_stats` |

## 설치

### 1) GitHub 저장소 페이지 URL을 직접 넣지 말 것
G7의 수동 설치는 보통 저장소 메인 페이지 URL이 아니라 ZIP 아카이브 URL 또는 로컬 클론 디렉터리로 설치해야 합니다.

```text
https://github.com/keidischoi/sirsoft-sales_stats
```

### 2) 로컬 클론 설치
```bash
cd /path/to/g7-project/modules
git clone https://github.com/keidischoi/sirsoft-sales_stats.git sirsoft-sales_stats
cd /path/to/g7-project
php artisan extension:update-autoload
php artisan module:install sirsoft-sales_stats --force
php artisan module:activate sirsoft-sales_stats
composer dump-autoload
php artisan optimize:clear
```

### 3) ZIP 수동 설치
- 위 ZIP 링크를 다운로드해 G7 관리자에서 수동 설치
- 설치 후 반드시 activate

**반드시 activate** 해야 레이아웃·프론트 라우트가 등록됩니다.

## 접속

- 메뉴 **판매 통계** (`fas fa-chart-bar`)
- URL: `/admin/ecommerce/sales-stats`
- 일별/월별/연간 버튼으로 집계 단위 변경
- 요약 카드 + 기간/상품/카테고리 테이블 + CSV

## 화면이 깜빡이며 404면

1. `php artisan module:activate sirsoft-sales_stats`
2. `php artisan optimize:clear`
3. 브라우저 강력 새로고침
4. URL이 정확히 `/admin/ecommerce/sales-stats` 인지 확인
