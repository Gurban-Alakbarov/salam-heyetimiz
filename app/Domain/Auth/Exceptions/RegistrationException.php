<?php

namespace App\Domain\Auth\Exceptions;

/**
 * Registration-flow business failures (already-verified email, taken phone). Deliberately NOT a
 * DomainException: the registration endpoints render the unified envelope themselves, so the controller
 * catches this and maps it to `{ success:false, message, errors }` rather than the legacy `{error}`
 * renderer.
 */
final class RegistrationException extends \Exception
{
    /**
     * @param  array<string, array<int, string>>|null  $fieldErrors
     */
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly ?array $fieldErrors = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public static function alreadyRegistered(): self
    {
        return new self(409, 'Bu email artıq qeydiyyatdan keçib. Zəhmət olmasa giriş edin.', errorCode: 'email_already_registered');
    }

    public static function phoneTaken(): self
    {
        return new self(422, 'Doğrulama xətası baş verdi.', fieldErrors: ['phone' => ['Bu telefon nömrəsi artıq istifadədədir.']]);
    }

    /**
     * K1 — the phone belongs to an unverified account with live access whose recorded email is not this one.
     * A phone number alone never hands that account over; support records the owner's email first.
     */
    public static function phoneLinkedToAccount(): self
    {
        return new self(409, 'Bu telefon nömrəsi mövcud hesaba bağlıdır. Hesabı aktivləşdirmək üçün dəstək xidmətinə email ünvanınızı bildirin, sonra həmin email ilə qeydiyyatdan keçin.', errorCode: 'phone_linked_to_account');
    }

    public static function conflict(): self
    {
        return new self(409, 'Bu məlumatlarla qeydiyyat mümkün deyil. Dəstək ilə əlaqə saxlayın.', errorCode: 'registration_conflict');
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->fieldErrors ?? ['code' => $this->errorCode];
    }
}
