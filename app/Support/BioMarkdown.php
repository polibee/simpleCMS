<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/**
 * 个人简介 Markdown 渲染（与评论同安全策略：原始 HTML 转义防 XSS）。
 */
final class BioMarkdown
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(?string $markdown): ?string
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return null;
        }

        return self::converter()->convert($markdown)->getContent();
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $environment = new Environment(['html_input' => 'escape']);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new TaskListExtension);

        return self::$converter = new MarkdownConverter($environment);
    }
}
