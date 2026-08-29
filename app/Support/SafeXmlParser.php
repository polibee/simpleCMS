<?php

namespace App\Support;

/**
 * XML 安全解析器。
 *
 * 安全约束：解析不可信 XML 时，解析前拒绝 DOCTYPE/ENTITY，不启用外部实体，
 * 禁用网络访问（LIBXML_NONET），防止 XXE（外部实体注入）攻击。
 */
final class SafeXmlParser
{
    /**
     * 将不可信 XML 字符串安全解析为 SimpleXMLElement。
     *
     * @throws \InvalidArgumentException 当 XML 含 DOCTYPE/ENTITY 或解析失败时
     */
    public static function parse(string $xml): \SimpleXMLElement
    {
        if (self::containsDtd($xml)) {
            throw new \InvalidArgumentException('XML 包含 DOCTYPE/ENTITY，已拒绝解析');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // LIBXML_NONET 禁止网络访问；不设置 LIBXML_NOENT / substituteEntities，实体不展开
            $element = simplexml_load_string(
                $xml,
                \SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOERROR
            );

            if ($element === false) {
                $errors = libxml_get_errors();
                $message = $errors !== []
                    ? trim($errors[0]->message)
                    : 'XML 解析失败';
                libxml_clear_errors();

                throw new \InvalidArgumentException($message);
            }

            return $element;
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * 检查 XML 是否包含 DTD / ENTITY 声明（宽松但有效的预检）。
     */
    public static function containsDtd(string $xml): bool
    {
        // 去除 XML 声明与注释后查找 DOCTYPE / ENTITY 关键字
        $stripped = preg_replace('/<!--.*?-->/s', '', $xml) ?? $xml;
        $stripped = preg_replace('/^<\?xml[^>]*\?>/i', '', $stripped) ?? $stripped;

        return (bool) preg_match('/<!DOCTYPE\b|<!ENTITY\b/i', $stripped);
    }
}
