<?php

namespace Modules\Ads\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Ads\Models\Ad;

/**
 * 广告投放服务：为前端按位置聚合启用的广告（按 sort 升序）。
 */
final class AdService
{
    /**
     * @return array<string, list<array<string, mixed>>> [position => [ad payload]]
     */
    public static function mapForFrontend(): array
    {
        try {
            if (! Schema::hasTable('ads')) {
                return [];
            }

            return Ad::query()
                ->where('enabled', true)
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->groupBy('position')
                ->map(fn ($group) => $group->map(fn (Ad $ad) => [
                    'id' => $ad->id,
                    'type' => $ad->type,
                    'title' => $ad->title,
                    'text' => $ad->text,
                    'image_url' => $ad->image_url,
                    'link_url' => $ad->link_url,
                    'html' => $ad->html,
                    'paragraph' => $ad->paragraph,
                    'new_tab' => $ad->new_tab,
                ])->values()->all())
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
