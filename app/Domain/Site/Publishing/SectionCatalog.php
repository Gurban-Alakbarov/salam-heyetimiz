<?php

namespace App\Domain\Site\Publishing;

/**
 * The ready-made section types a website page is built from. Admins choose, order and fill sections, the design
 * stays in the template, so moving a section cannot break the page. Each type has a view: site.sections.<type>.
 */
final class SectionCatalog
{
    public const TYPES = [
        'home-hero' => 'Ana səhifə girişi',
        'page-hero' => 'Səhifə başlığı',
        'benefit-bar' => 'Üstünlüklər zolağı',
        'product-grid' => 'Məhsul kartları',
        'service-grid' => 'Xidmət kartları',
        'feature-grid' => 'İmkanlar',
        'steps' => 'Addımlar',
        'process' => 'Addımlar, şəkillərlə',
        'split' => 'Şəkil və mətn',
        'audience-cards' => 'Kimlər üçün kartları',
        'campaign-list' => 'Kampaniyalar',
        'cta-dark' => 'Tünd çağırış',
        'cta-center' => 'Mərkəzdə çağırış',
        'faq-short' => 'Qısa suallar',
        'faq-full' => 'Bütün suallar',
        'contact-form' => 'Müraciət forması',
        'legal' => 'Hüquqi mətn',
    ];

    public static function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
