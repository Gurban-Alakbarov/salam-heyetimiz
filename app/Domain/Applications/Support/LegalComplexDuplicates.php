<?php

namespace App\Domain\Applications\Support;

use App\Domain\Admin\Models\Complex;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Exceptions\ApplicationException;
use App\Domain\Applications\Models\LegalEntityApplication;

/**
 * Y8 — one company (VÖEN) may run several complexes, so a second application with the same VÖEN is fine for a
 * DIFFERENT complex; the same complex approved again would create a duplicate complex. Compared on the
 * normalised name of every approved application of that VÖEN and of the complex it created (even if renamed).
 * Checked on submit / edit and again on approval (an older pending duplicate never creates a second complex).
 */
final class LegalComplexDuplicates
{
    public function assertNotApproved(string $voen, string $complexName): void
    {
        $approved = LegalEntityApplication::query()->where('voen', $voen)
            ->where('status', LegalEntityApplicationStatus::Approved->value)
            ->get(['id', 'complex_name']);
        if ($approved->isEmpty()) {
            return;
        }

        $names = $approved->pluck('complex_name')
            ->merge(Complex::query()->whereIn('legal_entity_application_id', $approved->pluck('id'))->pluck('name'))
            ->map(fn ($name): string => self::normalise((string) $name));

        if ($names->contains(self::normalise($complexName))) {
            throw ApplicationException::duplicateComplex();
        }
    }

    /**
     * "«GƏNCLİK Park»  YK" ≡ "gənclik park yk": case, quotes, punctuation and spacing do not make a new complex.
     * Azerbaijani I/İ/ı/i all compare as `i` (mb_strtolower('İ') is "i" + a combining dot otherwise).
     */
    public static function normalise(string $name): string
    {
        $name = mb_strtolower(str_replace(['İ', 'I', 'ı'], 'i', trim($name)));
        $name = str_replace("\u{0307}", '', $name);
        $name = preg_replace('/["\'`«»“”„.,\-–—]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }
}
