<?php

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

/**
 * 统一内容契约：CMS 的 Article 与 Forum 的 Topic 都实现它。
 * Quest / AI / Search 等模块只依赖本接口，不感知内容类型。
 */
interface ContentContract
{
    public function contentId(): int|string;

    public function contentTitle(): string;

    public function contentBody(): string;

    public function author(): ?Authenticatable;

    public function publishedAt(): ?Carbon;

    public function contentUrl(): string;

    public function contentType(): string; // 'cms.article' | 'forum.topic'
}
