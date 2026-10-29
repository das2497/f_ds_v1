<?php

namespace App\Services\Html;

/**
 * Allow-list HTML sanitizer shared by anything that stores a rich-text
 * product description: vendor-supplied HTML (CJService) and admin-authored
 * HTML from the rich-text editor on the product create/edit forms. Strips
 * everything outside a small set of text-formatting tags and drops every
 * attribute, since a description never needs more than that.
 */
class DescriptionSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'h3', 'h4', 'h5'];

    private const DROP_TAGS = ['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg', 'form', 'input', 'button'];

    public static function sanitize(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $container = $dom->getElementsByTagName('div')->item(0);

        if (! $container) {
            return null;
        }

        self::stripDisallowedNodes($container);

        $clean = '';
        foreach (iterator_to_array($container->childNodes) as $child) {
            $clean .= $dom->saveHTML($child);
        }

        $clean = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br>', $clean) ?? $clean;
        $clean = preg_replace('/<p>(\s|&nbsp;)*<\/p>/i', '', $clean) ?? $clean;
        $clean = preg_replace('/^(?:<br\s*\/?>\s*)+/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(?:<br\s*\/?>\s*)+$/i', '', $clean) ?? $clean;

        $clean = trim($clean);

        return $clean !== '' ? $clean : null;
    }

    private static function stripDisallowedNodes(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            self::stripDisallowedNodes($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $child->removeAttribute($attribute->name);
            }
        }
    }
}
