<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * 文章正文 HTML 清洗（创作中心保存前调用）。
 *
 * 作者是管理员授予角色的半可信用户，不能用原始 HTML 直存：这里基于
 * symfony/html-sanitizer 的白名单过滤脚本与危险属性，保留常见排版
 * 标签、链接与图片，防止存储型 XSS。
 */
final class HtmlSanitizer
{
    private static ?SymfonySanitizer $instance = null;

    public static function clean(?string $html): string
    {
        $html ??= '';

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonySanitizer
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = (new HtmlSanitizerConfig())
            // 安全基线：p/br/ul/ol/li/blockquote/strong/em/code/h1-h6/a/img 等
            ->allowSafeElements()
            // 代码块：基线不含 <pre>
            ->allowElement('pre')
            ->allowElement('hr')
            // 链接协议白名单
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowAttribute('target', ['a']) // 编辑器插入的链接默认新窗口打开
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            // 图片协议白名单
            ->allowMediaSchemes(['https', 'http']);

        return self::$instance = new SymfonySanitizer($config);
    }
}