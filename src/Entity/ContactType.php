<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How a user wants to be reached once a contact request is relayed to them.
 *
 * The case translates itself so the form choices and the account page can render a label
 * without either of them knowing the mapping.
 */
enum ContactType: string implements TranslatableInterface
{
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Viber = 'viber';
    case Facebook = 'facebook';
    case Other = 'other';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('contact.type.'.$this->value, locale: $locale);
    }
}
