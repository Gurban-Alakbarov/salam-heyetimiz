<?php

namespace App\Domain\Site\Publishing;

/**
 * The ready-made section types a website page is built from. Admins choose, order and fill sections, the design
 * stays in the template, so moving a section cannot break the page. Each type has a view: site.sections.<type>.
 *
 * Field kinds: text (translated, plain), inline (translated, bold, italic, line break, link), rich (translated,
 * paragraphs and lists too), link (translated label + address), image, icon, date, anchor, group, list.
 */
final class SectionCatalog
{
    /** @return array<string, array{label: string, fields: array<string, array<string, mixed>>}> */
    public static function types(): array
    {
        $cards = ['image' => self::image('Şəkil'), 'image_alt' => self::text('Şəklin təsviri')];
        $faq = self::lists('Suallar', ['q' => self::text('Sual'), 'a' => self::text('Cavab')]);

        return [
            'home-hero' => self::type('Ana səhifə girişi', [
                'kicker' => self::text('Üst yazı'),
                'title' => self::inline('Başlıq'),
                'text' => self::text('Mətn'),
                'primary' => self::link('Əsas düymə'),
                'secondary' => self::link('İkinci düymə'),
                'smallprint' => self::text('Kiçik yazı'),
                ...$cards,
                'phone' => ['kind' => 'group', 'label' => 'Telefon nümunəsi', 'fields' => [
                    'aria' => self::text('Telefonun təsviri'),
                    'title' => self::text('Ekran başlığı'),
                    'list_label' => self::text('Siyahı başlığı'),
                    'device_image' => self::image('Cihaz şəkli'),
                    'device_image_alt' => self::text('Cihaz şəklinin təsviri'),
                    'online' => self::text('Onlayn yazısı'),
                    'device_name' => self::text('Cihaz adı'),
                    'device_place' => self::text('Cihazın yeri'),
                    'invite' => self::text('Dəvət yazısı'),
                    'directions' => self::text('Yol yazısı'),
                    'remote' => self::text('Pult yazısı'),
                    'response' => self::text('Pult cavabı'),
                    'small_name' => self::text('İkinci cihaz'),
                    'small_place' => self::text('İkinci cihazın yeri'),
                    'tab_home' => self::text('Menyu: ana səhifə'),
                    'tab_devices' => self::text('Menyu: cihazlar'),
                    'tab_profile' => self::text('Menyu: profil'),
                ]],
                'badge_title' => self::text('Nişan başlığı'),
                'badge_text' => self::text('Nişan mətni'),
            ]),
            'page-hero' => self::type('Səhifə başlığı', [
                'kicker' => self::text('Üst yazı'),
                'title' => self::inline('Başlıq'),
                'text' => self::text('Mətn'),
                ...$cards,
            ]),
            'benefit-bar' => self::type('Üstünlüklər zolağı', [
                'items' => self::lists('Üstünlüklər', ['icon' => self::icon(), 'title' => self::text('Başlıq'), 'text' => self::text('Mətn')]),
            ]),
            'product-grid' => self::type('Məhsul kartları', [
                'id' => ['kind' => 'anchor', 'label' => 'Lövbər adı'],
                ...self::heading(),
                'items' => self::lists('Kartlar', [...$cards, 'href' => self::address('Keçid'), 'title' => self::text('Başlıq'), 'text' => self::text('Mətn')]),
                'link' => self::link('Aşağı keçid'),
            ]),
            'service-grid' => self::type('Xidmət kartları', [
                ...self::heading(),
                'items' => self::lists('Kartlar', [...$cards, 'title' => self::text('Başlıq'), 'text' => self::text('Mətn')]),
            ]),
            'feature-grid' => self::type('İmkanlar', [
                ...self::heading(),
                'items' => self::lists('İmkanlar', ['icon' => self::icon(), 'title' => self::text('Başlıq'), 'text' => self::text('Mətn')]),
            ]),
            'steps' => self::type('Addımlar', [
                ...self::heading(),
                'items' => self::lists('Addımlar', ['title' => self::text('Başlıq'), 'text' => self::text('Mətn')]),
                'link' => self::link('Aşağı keçid'),
            ]),
            'process' => self::type('Addımlar, şəkillərlə', [
                ...self::heading(),
                'items' => self::lists('Addımlar', ['title' => self::text('Başlıq'), 'text' => self::text('Mətn'), 'link' => self::link('Keçid'), ...$cards]),
                'note' => self::inline('Qeyd'),
            ]),
            'split' => self::type('Şəkil və mətn', [
                ...$cards,
                'kicker' => self::text('Üst yazı'),
                'title' => self::inline('Başlıq'),
                'text' => self::text('Mətn'),
                'checks' => self::lists('Siyahı', ['text' => self::text('Mətn')]),
                'button' => self::link('Düymə'),
            ]),
            'audience-cards' => self::type('Kimlər üçün kartları', [
                ...self::heading(),
                'items' => self::lists('Kartlar', [...$cards, 'title' => self::text('Başlıq'), 'text' => self::text('Mətn'), 'link' => self::link('Keçid')]),
            ]),
            'campaign-list' => self::type('Kampaniyalar', [
                'until_label' => self::text('Son tarix yazısı'),
                'items' => self::lists('Kampaniyalar', [
                    ...$cards,
                    'title' => self::text('Başlıq'),
                    'text' => self::text('Mətn'),
                    'ends_at' => ['kind' => 'date', 'label' => 'Son gün'],
                    'link' => self::link('Keçid'),
                ]),
            ]),
            'cta-dark' => self::type('Tünd çağırış', [
                'kicker' => self::text('Üst yazı'),
                'title' => self::inline('Başlıq'),
                'text' => self::text('Mətn'),
                'button' => self::link('Düymə'),
            ]),
            'cta-center' => self::type('Mərkəzdə çağırış', [
                'title' => self::inline('Başlıq'),
                'text' => self::text('Mətn'),
                'button' => self::link('Düymə'),
            ]),
            'faq-short' => self::type('Qısa suallar', [
                ...self::heading(),
                'items' => $faq,
                'side_title' => self::text('Yan blok başlığı'),
                'side_text' => self::text('Yan blok mətni'),
                'side_button' => self::link('Yan blok düyməsi'),
                'link' => self::link('Aşağı keçid'),
            ]),
            'faq-full' => self::type('Bütün suallar', [
                'items' => $faq,
                'side_title' => self::text('Yan blok başlığı'),
                'side_text' => self::text('Yan blok mətni'),
                'side_button' => self::link('Yan blok düyməsi'),
            ]),
            'contact-form' => self::type('Müraciət forması', [
                ...$cards,
                'photo_kicker' => self::text('Şəkil üzərində üst yazı'),
                'photo_title' => self::inline('Şəkil üzərində başlıq'),
                'photo_text' => self::text('Şəkil üzərində mətn'),
                'kicker' => self::text('Forma üst yazısı'),
                'title' => self::inline('Forma başlığı'),
                'text' => self::text('Forma mətni'),
                'name_label' => self::text('Ad sahəsi'),
                'name_hint' => self::text('Ad nümunəsi'),
                'email_label' => self::text('E-poçt sahəsi'),
                'email_hint' => self::text('E-poçt nümunəsi'),
                'phone_label' => self::text('Telefon sahəsi'),
                'phone_hint' => self::text('Telefon nümunəsi'),
                'place_label' => self::text('Məkan sahəsi'),
                'place_hint' => self::text('Məkan yer tutucusu'),
                'places' => self::lists('Məkan növləri', ['text' => self::text('Mətn')]),
                'topic_label' => self::text('Mövzu sahəsi'),
                'topics' => self::lists('Mövzular', ['text' => self::text('Mətn')]),
                'message_label' => self::text('Mesaj sahəsi'),
                'message_hint' => self::text('Mesaj nümunəsi'),
                'consent' => self::inline('Razılıq yazısı'),
                'submit' => self::text('Göndər düyməsi'),
                'done_title' => self::text('Uğur başlığı'),
                'done_text' => self::text('Uğur mətni'),
            ]),
            'legal' => self::type('Hüquqi mətn', [
                'company' => self::text('Şirkət'),
                'items' => self::lists('Maddələr', ['title' => self::text('Başlıq'), 'body' => ['kind' => 'rich', 'label' => 'Mətn']]),
                'button' => self::link('Düymə'),
            ]),
        ];
    }

    public static function has(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    /** Fields of a page apart from its sections. */
    public static function pageFields(): array
    {
        return [
            'title' => self::text('Səhifə adı'),
            'description' => self::text('Axtarış təsviri'),
            'share_title' => self::text('Paylaşım başlığı'),
            'share_text' => self::text('Paylaşım mətni'),
        ];
    }

    /** Texts shared by all pages. */
    public static function sharedFields(): array
    {
        return [
            'logo_aria' => self::text('Loqo üçün təsvir'),
            'nav_aria' => self::text('Menyu üçün təsvir'),
            'mobile_nav_aria' => self::text('Mobil menyu üçün təsvir'),
            'lang_aria' => self::text('Dil seçimi üçün təsvir'),
            'menu_open' => self::text('Menyunu aç'),
            'menu_close' => self::text('Menyunu bağla'),
            'contact_label' => self::text('Menyuda əlaqə'),
            'cta_label' => self::text('Menyu düyməsi'),
            'footer_about' => self::inline('Aşağıda haqqımızda'),
            'footer_company' => self::text('Aşağıda şirkət'),
            'footer_site_title' => self::text('Aşağıda sayt başlığı'),
            'footer_contact_item' => self::text('Aşağıda əlaqə linki'),
            'footer_legal_title' => self::text('Aşağıda hüquqi başlıq'),
            'footer_contact_title' => self::text('Aşağıda əlaqə başlığı'),
            'footer_contact_text' => self::text('Aşağıda əlaqə mətni'),
            'footer_action' => self::text('Aşağıda düymə'),
            'copyright' => self::text('Müəllif hüququ'),
            'region' => self::text('Region'),
            'legal_sidebar_title' => self::text('Hüquqi menyu başlığı'),
            'legal_support_title' => self::text('Hüquqi dəstək başlığı'),
            'legal_support_text' => self::text('Hüquqi dəstək mətni'),
            'legal_support_link' => self::text('Hüquqi dəstək linki'),
            'share_image_alt' => self::text('Paylaşım şəklinin təsviri'),
            'msg_demo' => self::text('Demo pult xəbərdarlığı'),
            'msg_busy' => self::text('Forma: çox müraciət'),
            'msg_failed' => self::text('Forma: göndərilmədi'),
            'msg_offline' => self::text('Forma: bağlantı xətası'),
        ];
    }

    private static function type(string $label, array $fields): array
    {
        return ['label' => $label, 'fields' => $fields];
    }

    private static function heading(): array
    {
        return ['kicker' => self::text('Üst yazı'), 'title' => self::inline('Başlıq'), 'text' => self::text('Mətn')];
    }

    private static function text(string $label): array
    {
        return ['kind' => 'text', 'label' => $label];
    }

    private static function inline(string $label): array
    {
        return ['kind' => 'inline', 'label' => $label];
    }

    private static function image(string $label): array
    {
        return ['kind' => 'image', 'label' => $label];
    }

    private static function icon(): array
    {
        return ['kind' => 'icon', 'label' => 'İkon'];
    }

    private static function address(string $label): array
    {
        return ['kind' => 'address', 'label' => $label];
    }

    private static function link(string $label): array
    {
        return ['kind' => 'link', 'label' => $label];
    }

    private static function lists(string $label, array $fields): array
    {
        return ['kind' => 'list', 'label' => $label, 'fields' => $fields];
    }
}
