<?php

namespace App\Domain\Site\Content;

use App\Domain\Site\Publishing\SectionCatalog;
use App\Domain\Site\Publishing\SiteBuilder;
use Illuminate\Validation\ValidationException;

/**
 * Turns edited content into the exact shape the templates expect: unknown keys go, missing keys come back empty,
 * HTML is cleaned. Unfinished text is fine in a draft. What cannot be right (a bad address, a section type that
 * does not exist) is refused, with the path of the field so the panel can point at it.
 */
final class ContentNormalizer
{
    private const MAX_SECTIONS = 40;

    private const MAX_ITEMS = 60;

    private const MAX_TEXT = 2000;

    private const MAX_RICH = 40000;

    private const IMAGE = '~^/landing/[A-Za-z0-9._/-]+$~';

    /** @var array<string, string> */
    private array $errors = [];

    /** @param list<string> $icons Icon names that exist */
    public function __construct(private readonly array $icons) {}

    /** @param array<string, mixed> $input */
    public function page(array $input, string $key, array $current = []): array
    {
        $content = ['key' => $key];
        foreach (SectionCatalog::pageFields() as $name => $field) {
            $content[$name] = $this->value($field, $input[$name] ?? null, $name);
        }

        $sections = is_array($input['sections'] ?? null) ? array_values($input['sections']) : [];
        if (count($sections) > self::MAX_SECTIONS) {
            $this->errors['sections'] = 'Bölmələr çox sayda.';
        }

        $types = SectionCatalog::types();
        $content['sections'] = [];
        foreach ($sections as $i => $section) {
            $type = is_array($section) ? ($section['type'] ?? null) : null;
            if (! is_string($type) || ! isset($types[$type])) {
                $this->errors["sections.{$i}.type"] = 'Belə bölmə növü yoxdur.';

                continue;
            }
            $content['sections'][] = ['type' => $type, ...$this->fields($types[$type]['fields'], $section, "sections.{$i}")];
        }

        return $this->finish($content, $current);
    }

    /** Only the texts change here. The menus are part of the structure, and stay as they are. */
    public function shared(array $input, array $current): array
    {
        $content = $current;
        $content['texts'] = $this->fields(SectionCatalog::sharedFields(), is_array($input['texts'] ?? null) ? $input['texts'] : [], 'texts');

        return $this->finish($content, $current);
    }

    private function finish(array $content, array $current): array
    {
        if ($this->errors !== []) {
            $errors = $this->errors;
            $this->errors = [];

            throw ValidationException::withMessages($errors);
        }

        return (new TranslationState)->stamp($content, $current);
    }

    private function fields(array $fields, array $input, string $path): array
    {
        $out = [];
        foreach ($fields as $name => $field) {
            $out[$name] = $this->value($field, $input[$name] ?? null, "{$path}.{$name}");
        }

        return $out;
    }

    private function value(array $field, mixed $in, string $path): mixed
    {
        return match ($field['kind']) {
            'text' => $this->translated($in, $path, fn (string $s) => $s, self::MAX_TEXT),
            'inline' => $this->translated($in, $path, SiteHtml::inline(...), self::MAX_TEXT),
            'rich' => $this->translated($in, $path, SiteHtml::rich(...), self::MAX_RICH),
            'link' => [
                'label' => $this->translated(is_array($in) ? ($in['label'] ?? null) : null, "{$path}.label", fn (string $s) => $s, self::MAX_TEXT),
                'href' => $this->address(is_array($in) ? ($in['href'] ?? '') : '', "{$path}.href"),
            ],
            'address' => $this->address($in ?? '', $path),
            'image' => $this->matching($in, $path, self::IMAGE, 'Şəkil ünvanı düzgün deyil.'),
            'anchor' => $this->matching($in, $path, '~^[a-z][a-z0-9-]*$~', 'Lövbər adı yalnız kiçik latın hərfləri ola bilər.'),
            'icon' => $this->icon($in, $path),
            'date' => $this->date($in, $path),
            'group' => $this->fields($field['fields'], is_array($in) ? $in : [], $path),
            'list' => $this->items($field, $in, $path),
        };
    }

    private function items(array $field, mixed $in, string $path): array
    {
        $items = is_array($in) ? array_values($in) : [];
        if (count($items) > self::MAX_ITEMS) {
            $this->errors[$path] = 'Siyahı çox uzundur.';
        }

        return array_map(
            fn (mixed $item, int $i) => $this->fields($field['fields'], is_array($item) ? $item : [], "{$path}.{$i}"),
            $items,
            array_keys($items),
        );
    }

    private function translated(mixed $in, string $path, callable $clean, int $max): array
    {
        $out = [];
        foreach (SiteBuilder::LANGS as $lang) {
            $text = is_array($in) ? ($in[$lang] ?? '') : '';
            if (! is_string($text)) {
                $text = '';
            }
            $text = $clean(trim($text));
            if (mb_strlen($text) > $max) {
                $this->errors["{$path}.{$lang}"] = "Mətn çox uzundur (ən çoxu {$max} simvol).";
            }
            $out[$lang] = $text;
        }
        if (is_array($in) && is_array($in[TranslationState::CONFIRMED] ?? null)) {
            $out[TranslationState::CONFIRMED] = $in[TranslationState::CONFIRMED];
        }

        return $out;
    }

    private function address(mixed $in, string $path): string
    {
        $href = is_string($in) ? trim($in) : '';
        if ($href !== '' && ! SiteHtml::safeHref($href)) {
            $this->errors[$path] = 'Keçid ünvanı düzgün deyil.';
        }

        return $href;
    }

    private function matching(mixed $in, string $path, string $pattern, string $message): string
    {
        $value = is_string($in) ? trim($in) : '';
        if ($value !== '' && preg_match($pattern, $value) !== 1) {
            $this->errors[$path] = $message;
        }

        return $value;
    }

    private function icon(mixed $in, string $path): string
    {
        $name = is_string($in) ? $in : '';
        if (! in_array($name, $this->icons, true)) {
            $this->errors[$path] = 'Belə ikon yoxdur.';
        }

        return $name;
    }

    private function date(mixed $in, string $path): string
    {
        $date = is_string($in) ? trim($in) : '';
        if ($date !== '' && (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
            $this->errors[$path] = 'Tarix düzgün deyil.';
        }

        return $date;
    }
}
