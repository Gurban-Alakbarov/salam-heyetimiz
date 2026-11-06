<?php

namespace App\Domain\Site\Content;

/**
 * Tracks whether en/ru still follow the Azerbaijani text. Next to each translation sits a fingerprint of the
 * Azerbaijani it was written from; when the Azerbaijani changes, the fingerprint no longer matches and that
 * translation needs an update. It lives on the value itself, so it stays right when sections move.
 */
final class TranslationState
{
    public const LANGS = ['en', 'ru'];

    /** Fingerprints stored on a translated value. */
    public const SOURCE = '_src';

    /** Sent by the panel: "this translation is still right", for example after a typo fix in Azerbaijani. */
    public const CONFIRMED = '_confirmed';

    /** Adds fingerprints to $content. A translation unchanged since $previous keeps its old one. */
    public function stamp(array $content, array $previous): array
    {
        $known = [];
        $this->collect($previous, $known);

        return $this->stampValues($content, $known);
    }

    /** @return list<array{path: string, lang: string, state: 'missing'|'stale'}> */
    public function issues(array $content): array
    {
        $issues = [];
        $this->inspect($content, '', $issues);

        return $issues;
    }

    public static function isTranslated(mixed $value): bool
    {
        return is_array($value) && array_key_exists('az', $value);
    }

    private static function fingerprint(string $text): string
    {
        return substr(md5($text), 0, 10);
    }

    /** @param array<string, array<string, list<string>>> $known */
    private function collect(array $value, array &$known): void
    {
        if (self::isTranslated($value)) {
            foreach (self::LANGS as $lang) {
                if (($value[$lang] ?? '') !== '' && isset($value[self::SOURCE][$lang])) {
                    $known[$lang][$value[$lang]][] = $value[self::SOURCE][$lang];
                }
            }

            return;
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collect($child, $known);
            }
        }
    }

    private function stampValues(array $value, array $known): array
    {
        if (self::isTranslated($value)) {
            return $this->stampOne($value, $known);
        }
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->stampValues($child, $known);
            }
        }

        return $value;
    }

    private function stampOne(array $value, array $known): array
    {
        $now = self::fingerprint($value['az']);
        $confirmed = $value[self::CONFIRMED] ?? [];
        $sources = [];
        foreach (self::LANGS as $lang) {
            if ($value[$lang] === '') {
                continue;
            }
            $earlier = $known[$lang][$value[$lang]] ?? [];
            $sources[$lang] = $earlier === [] || in_array($now, $earlier, true) || in_array($lang, $confirmed, true)
                ? $now
                : $earlier[0];
        }
        unset($value[self::CONFIRMED]);
        $value[self::SOURCE] = $sources;

        return $value;
    }

    /** @param list<array{path: string, lang: string, state: string}> $issues */
    private function inspect(array $value, string $path, array &$issues): void
    {
        if (self::isTranslated($value)) {
            if ($value['az'] === '') {
                return;
            }
            $now = self::fingerprint($value['az']);
            foreach (self::LANGS as $lang) {
                if ($value[$lang] === '') {
                    $issues[] = ['path' => $path, 'lang' => $lang, 'state' => 'missing'];
                } elseif (($value[self::SOURCE][$lang] ?? null) !== $now) {
                    $issues[] = ['path' => $path, 'lang' => $lang, 'state' => 'stale'];
                }
            }

            return;
        }
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $this->inspect($child, $path === '' ? (string) $key : "{$path}.{$key}", $issues);
            }
        }
    }
}
