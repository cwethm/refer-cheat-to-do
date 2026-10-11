<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\I18n\DateTime;

/**
 * Seeds an integration-test session exactly as a successful sign-in writes it.
 *
 * Since Slice 19 a session is only accepted when it carries the account's current credential
 * version and was issued within the absolute session lifetime. Fixture accounts start at
 * credential version 1.
 */
trait SignedInSessionTrait
{
    /**
     * Sign the next request in as the given user.
     */
    protected function signIn(int $userId, int $credentialVersion = 1, ?int $authenticatedAt = null): void
    {
        $this->session(['Auth' => [
            'user_id' => $userId,
            'credential_version' => $credentialVersion,
            'authenticated_at' => $authenticatedAt ?? DateTime::now()->getTimestamp(),
        ]]);
    }
}
