<?php

namespace App\Domain\Site\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Keeps only the markup an admin may put on the public website. Pages render this without escaping, so it is the
 * line between edited text and a script running for every visitor: everything not listed here is removed.
 */
final class SiteHtml
{
    private const INLINE = ['em', 'strong', 'br', 'a'];

    private const RICH = [...self::INLINE, 'p', 'ul', 'ol', 'li', 'h3'];

    /** Removed with everything inside; other unknown tags keep their text. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'head', 'title'];

    public static function inline(string $html): string
    {
        return self::clean($html, self::INLINE);
    }

    public static function rich(string $html): string
    {
        return self::clean($html, self::RICH);
    }

    /** Own pages, web addresses, e-mail and phone links. Nothing else, so no javascript: and no data:. */
    public static function safeHref(string $href): bool
    {
        $href = trim($href);

        return preg_match('~^(/(?!/)[^\s"<>]*|#[\w-]+|https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:\+?[\d\s()-]+)$~i', $href) === 1;
    }

    /** @param list<string> $allowed */
    private static function clean(string $html, array $allowed): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }
        self::walk($root, $allowed);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /** @param list<string> $allowed */
    private static function walk(DOMNode $parent, array $allowed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }
            if (! $node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions

                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::walk($node, $allowed);

            if (! in_array($tag, $allowed, true)) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $href = $tag === 'a' ? $node->getAttribute('href') : '';
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->name);
            }
            if ($tag === 'a' && self::safeHref($href)) {
                $node->setAttribute('href', trim($href));
            }
        }
    }
}
