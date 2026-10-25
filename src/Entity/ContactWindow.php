<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The window of the day a user agrees to be contacted in. "No preference" is a real answer, not
 * an absence of one, so it is a case of its own rather than a nullable column.
 */
enum ContactWindow: string implements TranslatableInterface
{
    case NoPreference = 'no_preference';
    case Morning = 'morning';
    case Midday = 'midday';
    case Evening = 'evening';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('contact.window.'.$this->value, locale: $locale);
    }
}
