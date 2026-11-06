<?php

namespace App\Domain\Site\Publishing;

use App\Domain\Site\Content\SiteMediaService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns the website content into a folder of static pages: one HTML file per page and language, plus /landing
 * assets. The website stays plain files, so it keeps working when the backend or the database is down.
 */
final class SiteBuilder
{
    public const LANGS = ['az', 'en', 'ru'];

    private const SHARE_LOCALES = ['az' => 'az_AZ', 'en' => 'en_US', 'ru' => 'ru_RU'];

    /** Images, styles and scripts: copied as they are, served from /landing. */
    private const ASSETS = 'landing';

    public function __construct(private readonly string $source) {}

    /** @return list<string> Pages written, relative to $out. */
    public function build(SiteContent $content, string $out): array
    {
        if (is_dir($out) && ! File::isEmptyDirectory($out)) {
            throw new RuntimeException("Build folder is not empty: {$out}");
        }
        File::ensureDirectoryExists($out);
        File::copyDirectory($this->source.'/'.self::ASSETS, $out.'/'.self::ASSETS);
        $media = Storage::disk('public')->path(SiteMediaService::FOLDER);
        if (is_dir($media)) {
            File::copyDirectory($media, $out.'/'.ltrim(SiteMediaService::PUBLISHED_PREFIX, '/'));
        }

        $written = [];
        foreach (self::LANGS as $lang) {
            foreach ($content->pages as $key => $page) {
                $file = ($lang === 'az' ? '' : $lang.'/').($key === 'index' ? 'index' : $key).'.html';
                File::ensureDirectoryExists(dirname($out.'/'.$file));
                File::put($out.'/'.$file, $this->renderPage($content, $page, $lang));
                $written[] = $file;
            }
        }

        File::put($out.'/sitemap.xml', $this->sitemap($content));
        File::put($out.'/robots.txt', $this->robots($content));

        return $written;
    }

    public function renderPage(SiteContent $content, array $page, string $lang): string
    {
        $t = fn (mixed $value): string => (string) (is_array($value) ? ($value[$lang] ?: ($value['az'] ?? '')) : $value);
        $url = fn (string $path): string => $this->localUrl($path, $lang);

        $html = view('site.layout', [
            'page' => $page,
            'lang' => $lang,
            'langs' => self::LANGS,
            'shareLocale' => self::SHARE_LOCALES[$lang],
            'site' => $content->site,
            'text' => array_map($t, $content->site['texts']),
            'pages' => $content->pages,
            'path' => $page['key'] === 'index' ? '/' : '/'.$page['key'],
            'version' => fn (string $file): string => substr((string) md5_file($this->source.'/'.self::ASSETS.'/'.$file), 0, 8),
            't' => $t,
            'url' => $url,
            'langUrl' => fn (string $path, string $to): string => $this->localUrl($path, $to),
            // Markup from the content: links follow the language, and a space between two tags survives the trim below.
            'html' => fn (mixed $value): string => (string) preg_replace('/>\s+</', '>&#32;<', $this->localizeLinks($t($value), $lang)),
            'icon' => fn (string $name, int $size): string => $this->icon($content, $name, $size),
        ])->render();

        // Template line breaks are not content: the original page had no space between its tags.
        return (string) preg_replace('/>\s+</', '><', trim($html))."\n";
    }

    /** Each page once per language, with its other languages listed, as search engines expect. */
    private function sitemap(SiteContent $content): string
    {
        $base = rtrim($content->site['base_url'], '/');
        $urls = '';
        foreach (array_keys($content->pages) as $key) {
            $path = $key === 'index' ? '/' : '/'.$key;
            $links = '';
            foreach (self::LANGS as $lang) {
                $links .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.e($base.$this->localUrl($path, $lang)).'"/>';
            }
            foreach (self::LANGS as $lang) {
                $urls .= '<url><loc>'.e($base.$this->localUrl($path, $lang)).'</loc>'.$links.'</url>'."\n";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n"
            .$urls.'</urlset>'."\n";
    }

    /** Personal links (guest passes, invitations) stay out of search results. */
    private function robots(SiteContent $content): string
    {
        return "User-agent: *\nDisallow: /v/\nDisallow: /invite/\nAllow: /\n\nSitemap: ".rtrim($content->site['base_url'], '/')."/sitemap.xml\n";
    }

    private function localUrl(string $path, string $lang): string
    {
        return $lang === 'az' ? $path : '/'.$lang.($path === '/' ? '/' : $path);
    }

    private function localizeLinks(string $html, string $lang): string
    {
        return (string) preg_replace_callback(
            '/href="(\/[^"]*)"/',
            fn (array $m) => 'href="'.$this->localUrl($m[1], $lang).'"',
            $html,
        );
    }

    private function icon(SiteContent $content, string $name, int $size): string
    {
        $drawing = $content->icons[$name] ?? throw new RuntimeException("Unknown website icon: {$name}");

        return '<svg aria-hidden="true" fill="none" height="'.$size.'" stroke="currentColor" stroke-linecap="round"'
            .' stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24" width="'.$size.'">'.$drawing.'</svg>';
    }
}
