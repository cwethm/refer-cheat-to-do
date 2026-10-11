<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Core\Configure;
use Cake\Mailer\Mailer;

/**
 * Plain-text messages that carry account lifecycle links (Slice 19).
 *
 * Links point at the client with the token in the URL fragment, so the token is never sent to
 * the server in a request line and never appears in access logs or `Referer` headers. The base
 * URL is `App.fullBaseUrl`, which `HostHeaderMiddleware` requires in production, so a forged
 * `Host` header cannot redirect a link.
 */
class AccountMailer extends Mailer
{
    /**
     * Invitation to create an account.
     */
    public function invitation(string $email, string $token, int $lifetimeSeconds): void
    {
        $this
            ->setTo($email)
            ->setEmailFormat('text')
            ->setSubject(sprintf('Your invitation to %s', self::appName()))
            ->setViewVars([
                'appName' => self::appName(),
                'link' => self::link('invite', $token),
                'hours' => intdiv($lifetimeSeconds, 3600),
            ])
            ->viewBuilder()
            ->setTemplate('invitation');
    }

    /**
     * Password reset link for an existing account.
     */
    public function passwordReset(string $email, string $token, int $lifetimeSeconds): void
    {
        $this
            ->setTo($email)
            ->setEmailFormat('text')
            ->setSubject(sprintf('Reset your %s password', self::appName()))
            ->setViewVars([
                'appName' => self::appName(),
                'link' => self::link('reset', $token),
                'minutes' => intdiv($lifetimeSeconds, 60),
            ])
            ->viewBuilder()
            ->setTemplate('password_reset');
    }

    /**
     * Client link carrying the token in the fragment.
     */
    public static function link(string $kind, string $token): string
    {
        return rtrim((string)Configure::read('App.fullBaseUrl'), '/') . '/app#' . $kind . '=' . $token;
    }

    /**
     * Application name shown in messages.
     */
    private static function appName(): string
    {
        return (string)Configure::read('App.name', 'refer-cheat-to-do');
    }
}
