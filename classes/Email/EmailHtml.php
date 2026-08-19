<?php

declare(strict_types=1);

final class EmailHtml
{
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="email-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $allowedTags = ['a','b','blockquote','br','div','em','h1','h2','h3','h4','hr','i','img','li','ol','p','span','strong','table','tbody','td','th','thead','tr','u','ul'];
        $allowedAttributes = ['alt','border','cellpadding','cellspacing','class','height','href','src','style','target','title','width'];
        $nodes = iterator_to_array($document->getElementsByTagName('*'));

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement || $node->getAttribute('id') === 'email-root') {
                continue;
            }

            if (!in_array(strtolower($node->tagName), $allowedTags, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                if (!in_array($name, $allowedAttributes, true)
                    || str_starts_with($name, 'on')
                    || (($name === 'href' || $name === 'src') && !self::safeUrl($value))
                    || ($name === 'style' && !self::safeStyle($value))) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($node->tagName === 'a' && $node->hasAttribute('target')) {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $root = $document->getElementById('email-root');
        if (!$root) {
            return '';
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    public static function textVersion(string $html): string
    {
        $withBreaks = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html) ?? $html;
        return trim(html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function safeUrl(string $url): bool
    {
        if ($url === '{UnsubscribeURL}') {
            return true;
        }
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        return (bool) preg_match('/^(https?:|mailto:|cid:)/i', $url);
    }

    private static function safeStyle(string $style): bool
    {
        return !preg_match('/expression\s*\(|javascript\s*:|@import|behavior\s*:|-moz-binding/i', $style);
    }
}
