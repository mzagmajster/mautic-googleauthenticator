<?php

declare(strict_types=1);

namespace MauticPlugin\HostnetAuthBundle\Helper;

/**
 * @see https://github.com/google/google-authenticator/wiki/Key-Uri-Format
 */
final class AuthenticatorHelper
{
    /**
     * @var int
     */
    private $secretLength;

    /**
     * @var int
     */
    private $pinModulo;

    /**
     * @var \DateTimeInterface
     */
    private $now;

    /**
     * @var int
     */
    private $codePeriod = 30;

    public function __construct(int $passCodeLength = 6, int $secretLength = 10, \DateTimeInterface $now = null)
    {
        $this->secretLength = $secretLength;
        $this->pinModulo    = 10 ** $passCodeLength;
        $this->now          = $now ?? new \DateTimeImmutable();
    }

    /**
     * @param string $secret
     * @param string $code
     */
    public function checkCode($secret, $code): bool
    {
        // current period
        if (hash_equals($this->getCode($secret, $this->now), $code)) {
            return true;
        }

        // previous period, happens if the user was slow to enter or it just crossed over
        $dateTime = new \DateTimeImmutable('@'.($this->now->getTimestamp() - $this->codePeriod));
        if (hash_equals($this->getCode($secret, $dateTime), $code)) {
            return true;
        }

        // next period, happens if the user is not completely synced and possibly a few seconds ahead
        $dateTime = new \DateTimeImmutable('@'.($this->now->getTimestamp() + $this->codePeriod));
        if (hash_equals($this->getCode($secret, $dateTime), $code)) {
            return true;
        }

        return false;
    }

    /**
     * NEXT_MAJOR: add the interface typehint to $time and remove deprecation.
     *
     * @param string                                   $secret
     * @param float|string|int|\DateTimeInterface|null $time
     */
    public function getCode($secret, /* \DateTimeInterface */ $time = null): string
    {
        if (null === $time) {
            $time = $this->now;
        }

        if ($time instanceof \DateTimeInterface) {
            $timeForCode = floor($time->getTimestamp() / $this->codePeriod);
        } else {
            @trigger_error(
                'Passing anything other than null or a DateTimeInterface to $time is deprecated as of 2.0 '.
                'and will not be possible as of 3.0.',
                E_USER_DEPRECATED
            );
            $timeForCode = $time;
        }

        $base32 = new NotationHelper(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true);
        $secret = $base32->decode($secret);

        $timeForCode = str_pad(pack('N', $timeForCode), 8, chr(0), STR_PAD_LEFT);

        $hash   = hash_hmac('sha1', $timeForCode, $secret, true);
        $offset = ord(substr($hash, -1));
        $offset &= 0xF;

        $truncatedHash = $this->hashToInt($hash, $offset) & 0x7FFFFFFF;

        return str_pad((string) ($truncatedHash % $this->pinModulo), 6, '0', STR_PAD_LEFT);
    }

    /**
     * NEXT_MAJOR: Remove this method.
     *
     * @param string $user
     * @param string $hostname
     * @param string $secret
     *
     * @deprecated deprecated as of 2.1 and will be removed in 3.0. Use Sonata\AuthenticatorHelper\GoogleQrUrl::generate() instead.
     */
    public function getUrl($user, $hostname, $secret): string
    {
        @trigger_error(sprintf(
            'Using %s() is deprecated as of 2.1 and will be removed in 3.0. '.
            'Use Sonata\AuthenticatorHelper\GoogleQrUrl::generate() instead.',
            __METHOD__
        ), E_USER_DEPRECATED);

        $issuer      = func_get_args()[3] ?? null;
        $accountName = sprintf('%s@%s', $user, $hostname);

        // manually concat the issuer to avoid a change in URL
        $url = QrHelper::generate($accountName, $secret);

        if ($issuer) {
            $url .= '%26issuer%3D'.$issuer;
        }

        return $url;
    }

    public function generateSecret(): string
    {
        return (new NotationHelper(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true))
            ->encode(random_bytes($this->secretLength));
    }

    private function hashToInt(string $bytes, int $start): int
    {
        return unpack('N', substr(substr($bytes, $start), 0, 4))[1];
    }
}
