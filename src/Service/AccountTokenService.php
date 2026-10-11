<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\AccountToken;
use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use DomainException;
use InvalidArgumentException;

/**
 * Issues and redeems the single-use links of the account lifecycle (Slice 19).
 *
 * A token is 32 random bytes, encoded as unpadded base64url. Only its SHA-256 hash is stored;
 * the plain value exists only in the link that is delivered. A token is usable once, until it
 * expires, for exactly the purpose it was issued for, and is compared in constant time.
 *
 * Redemption consumes the token with a conditional update inside the same transaction as the
 * account change, so two concurrent redemptions cannot both succeed and a rejected password
 * leaves the token usable.
 */
class AccountTokenService
{
    public const PURPOSE_INVITATION = 'invitation';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    /**
     * Shape of every token this service issues: 32 bytes as unpadded base64url.
     */
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve models.
     */
    public function __construct(private LocatorInterface $tables)
    {
    }

    /**
     * Issue an invitation for an address that has no account yet.
     *
     * Any outstanding invitation for the same address is retired, so only the newest link works.
     *
     * @return string|null The plain token, or `null` when the address already has an account.
     */
    public function issueInvitation(int $issuerUserId, string $email): ?string
    {
        $email = self::normalizeEmail($email);
        $users = $this->tables->get('Users');
        if ($users->exists(['email' => $email])) {
            return null;
        }

        return $users->getConnection()->transactional(function () use ($issuerUserId, $email): string {
            $this->retireOutstanding(['purpose' => self::PURPOSE_INVITATION, 'email' => $email]);

            return $this->store(self::PURPOSE_INVITATION, $email, null, $issuerUserId);
        });
    }

    /**
     * Issue a password-reset token for a known address.
     *
     * Any outstanding reset for the same account is retired, so only the newest link works.
     *
     * @return string|null The plain token, or `null` when no account uses the address.
     */
    public function issuePasswordReset(string $email): ?string
    {
        $email = self::normalizeEmail($email);
        $users = $this->tables->get('Users');
        /** @var \App\Model\Entity\User|null $user */
        $user = $users->find()->select(['id', 'email'])->where(['email' => $email])->first();
        if ($user === null) {
            return null;
        }

        return $users->getConnection()->transactional(function () use ($user): string {
            $userId = (int)$user->id;
            $this->retireOutstanding(['purpose' => self::PURPOSE_PASSWORD_RESET, 'user_id' => $userId]);

            return $this->store(self::PURPOSE_PASSWORD_RESET, (string)$user->email, $userId, null);
        });
    }

    /**
     * Create the invited account with the chosen password.
     *
     * @throws \DomainException When the token is unknown, used, expired or for another purpose.
     * @throws \InvalidArgumentException When the password is rejected; the token stays usable.
     */
    public function redeemInvitation(string $plainToken, string $password): User
    {
        $users = $this->tables->get('Users');

        return $users->getConnection()->transactional(function () use ($users, $plainToken, $password): User {
            $token = $this->consume($plainToken, self::PURPOSE_INVITATION);
            if ($users->exists(['email' => $token->email])) {
                throw new DomainException('This link is invalid or has expired.');
            }

            /** @var \App\Model\Entity\User $user */
            $user = $users->newEntity(['email' => $token->email, 'password' => $password]);
            if ($user->getErrors() !== [] || !$users->save($user)) {
                throw new InvalidArgumentException($this->passwordMessage($user));
            }

            return $user;
        });
    }

    /**
     * Replace the password of the account the reset token was issued for.
     *
     * Saving the password advances the account's credential version, which ends every existing
     * session, and retires any other outstanding reset link ({@see \App\Model\Table\UsersTable}).
     *
     * @throws \DomainException When the token is unknown, used, expired or for another purpose.
     * @throws \InvalidArgumentException When the password is rejected; the token stays usable.
     */
    public function redeemPasswordReset(string $plainToken, string $password): User
    {
        $users = $this->tables->get('Users');

        return $users->getConnection()->transactional(function () use ($users, $plainToken, $password): User {
            $token = $this->consume($plainToken, self::PURPOSE_PASSWORD_RESET);
            /** @var \App\Model\Entity\User|null $user */
            $user = $users->find()->where(['id' => (int)$token->user_id])->first();
            if ($user === null) {
                throw new DomainException('This link is invalid or has expired.');
            }

            $users->patchEntity($user, ['password' => $password], ['fields' => ['password']]);
            if ($user->getErrors() !== [] || !$users->save($user)) {
                throw new InvalidArgumentException($this->passwordMessage($user));
            }

            return $user;
        });
    }

    /**
     * SHA-256 hash under which a plain token is stored.
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Constant-time comparison of a presented token with a stored hash.
     */
    public static function matches(string $plainToken, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hashToken($plainToken));
    }

    /**
     * Lowercase and trim an address exactly like sign-in does.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Validity of each token purpose, in seconds.
     */
    public static function lifetime(string $purpose): int
    {
        $key = $purpose === self::PURPOSE_INVITATION ? 'invitationLifetime' : 'passwordResetLifetime';
        $seconds = Configure::read('Accounts.' . $key);

        return is_int($seconds) && $seconds > 0 ? $seconds : 3600;
    }

    /**
     * Store a new token and return its plain value.
     */
    private function store(string $purpose, string $email, ?int $userId, ?int $issuerUserId): string
    {
        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokens = $this->tables->get('AccountTokens');
        $token = $tokens->newEmptyEntity();
        $token->patch([
            'purpose' => $purpose,
            'email' => $email,
            'user_id' => $userId,
            'issued_by_user_id' => $issuerUserId,
            'token_hash' => self::hashToken($plainToken),
            'expires' => DateTime::now()->addSeconds(self::lifetime($purpose)),
            'created' => DateTime::now(),
        ], ['guard' => false]);
        $tokens->saveOrFail($token);

        return $plainToken;
    }

    /**
     * Mark unused tokens matching the conditions as used.
     *
     * @param array<string, mixed> $conditions
     */
    private function retireOutstanding(array $conditions): void
    {
        $this->tables->get('AccountTokens')->updateAll(
            ['used' => DateTime::now()],
            $conditions + ['used IS' => null],
        );
    }

    /**
     * Find a usable token and mark it used. Must run inside a transaction.
     *
     * @throws \DomainException When the token cannot be used.
     */
    private function consume(string $plainToken, string $purpose): AccountToken
    {
        $invalid = new DomainException('This link is invalid or has expired.');
        if (preg_match(self::TOKEN_PATTERN, $plainToken) !== 1) {
            throw $invalid;
        }

        $tokens = $this->tables->get('AccountTokens');
        $hash = self::hashToken($plainToken);
        /** @var \App\Model\Entity\AccountToken|null $token */
        $token = $tokens->find()
            ->where(['token_hash' => $hash, 'purpose' => $purpose])
            ->first();
        if ($token === null || !self::matches($plainToken, (string)$token->token_hash)) {
            throw $invalid;
        }

        $now = DateTime::now();
        $consumed = $tokens->updateAll(
            ['used' => $now],
            ['id' => (int)$token->id, 'used IS' => null, 'expires >' => $now],
        );
        if ($consumed !== 1) {
            throw $invalid;
        }

        return $token;
    }

    /**
     * First password validation message, or a generic one.
     */
    private function passwordMessage(User $user): string
    {
        $errors = $user->getError('password');
        $first = reset($errors);

        return is_string($first) && $first !== ''
            ? 'The password was not accepted: ' . $first
            : 'The password was not accepted.';
    }
}
