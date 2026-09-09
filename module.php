<?php

namespace Modules\Sirsoft\SalesStats;

use App\Extension\AbstractModule;
use Illuminate\Support\Facades\View;

/**
 * 판매 통계 모듈 (sirsoft-ecommerce 확장)
 */
class Module extends AbstractModule
{
    /**
     * @return array<int, mixed>
     */
    public function getRoles(): array
    {
        return [];
    }

    /**
     * 관리자 메뉴 — Font Awesome 아이콘 (이커머스와 동일)
     */
    public function getAdminMenus(): array
    {
        return [
            [
                'slug' => 'sirsoft-sales_stats-main',
                'name' => [
                    'ko' => '판매 통계',
                    'en' => 'Sales Statistics',
                ],
                'url' => '/admin/ecommerce/sales-stats',
                'icon' => 'fas fa-chart-bar',
                'order' => 45,
            ],
        ];
    }

    /**
     * 권한 — G7 계층 구조 (name/description 모든 레벨 필수)
     */
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '판매 통계',
                'en' => 'Sales Statistics',
            ],
            'description' => [
                'ko' => '이커머스 판매 통계 권한',
                'en' => 'Ecommerce sales statistics permissions',
            ],
            'type' => 'admin',
            'categories' => [
                [
                    'identifier' => 'stats',
                    'name' => [
                        'ko' => '통계',
                        'en' => 'Statistics',
                    ],
                    'description' => [
                        'ko' => '판매 통계 관련 권한',
                        'en' => 'Sales statistics related permissions',
                    ],
                    'permissions' => [
                        [
                            'action' => 'view',
                            'name' => [
                                'ko' => '판매 통계 조회',
                                'en' => 'View Sales Statistics',
                            ],
                            'description' => [
                                'ko' => '판매 통계를 조회할 수 있습니다.',
                                'en' => 'Can view sales statistics.',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function boot(): void
    {
        View::addNamespace('sirsoft-sales_stats', $this->getModulePath().'/resources/views');
    }

    public function getMetadata(): array
    {
        return [
            'author' => 'Sirsoft',
            'license' => 'MIT',
        ];
    }
}
