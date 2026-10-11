<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Mailer\AccountMailer;
use App\Model\Entity\User;
use App\Service\AccountTokenService;
use App\Service\AfterResponse;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use DomainException;
use InvalidArgumentException;

class AuthController extends AppController
{
    /**
     * Bcrypt hash of a discarded random value, verified against when an address is unknown so
     * a failed sign-in costs the same whether or not the account exists.
     */
    private const TIMING_EQUALIZER_HASH = '$2y$10$Ofqx1zQ4qEoXc8b4AQZr1eV6ZV6IPOoAVOAR9dmQGAIdYUTkPwUlC';

    /**
     * Response for every accepted reset request, whether or not an account uses the address.
     */
    public const RESET_REQUESTED_MESSAGE =
        'If an account uses that address, a link to reset its password has been sent to it.';

    /**
     * Authenticate with email and password and create session context.
     *
     * Failed attempts are rate limited per address and per client. A full bucket is refused
     * before the password is checked, so it cannot be used to confirm a guess.
     */
    public function login(): Response
    {
        $email = AccountTokenService::normalizeEmail((string)$this->request->getData('email', ''));
        $password = (string)$this->request->getData('password', '');

        if ($email === '' || $password === '') {
            throw new BadRequestException('Email and password are required.');
        }

        $limiter = $this->rateLimiter();
        $client = (string)$this->request->clientIp();
        $limiter->assertAvailable('login_client', $client);
        $limiter->assertAvailable('login_account', $email);

        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        /** @var \App\Model\Entity\User|null $user */
        $user = $users->find()
            ->where(['email' => $email])
            ->first();

        $verified = password_verify($password, $user === null ? self::TIMING_EQUALIZER_HASH : (string)$user->password);
        if ($user === null || !$verified) {
            $limiter->hit('login_client', $client);
            $limiter->hit('login_account', $email);

            throw new UnauthorizedException('Invalid credentials.');
        }

        $this->startAuthenticatedSession((int)$user->id, (int)$user->credential_version);

        return $this->respond([
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * End the session entirely, discarding its id and data.
     */
    public function logout(): Response
    {
        $this->request->getSession()->destroy();

        return $this->respond([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return the current authenticated user.
     */
    public function me(): Response
    {
        return $this->respond([
            'user' => $this->serializeUser($this->currentUser()),
        ]);
    }

    /**
     * Change the signed-in user's password after confirming the current one.
     *
     * The session id is regenerated and the credential version advances, so this session stays
     * signed in and every other session of the account ends.
     */
    public function changePassword(): Response
    {
        $user = $this->currentUser();
        $userId = (int)$user->id;
        $current = (string)$this->request->getData('current_password', '');
        $replacement = (string)$this->request->getData('new_password', '');
        if ($current === '' || $replacement === '') {
            throw new BadRequestException('The current and new passwords are required.');
        }

        $limiter = $this->rateLimiter();
        $limiter->assertAvailable('password_change_account', (string)$userId);
        if (!password_verify($current, (string)$user->password)) {
            $limiter->hit('password_change_account', (string)$userId);

            throw new ValidationException('The current password is incorrect.');
        }
        if (hash_equals($current, $replacement)) {
            throw new ValidationException('Choose a new password that differs from the current one.');
        }

        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        $users->patchEntity($user, ['password' => $replacement], ['fields' => ['password']]);
        if ($user->getErrors() !== []) {
            throw new ValidationException('The new password was not accepted: ' . $this->firstError($user));
        }
        if (!$users->save($user)) {
            throw new InternalErrorException('Unable to change the password.');
        }

        $version = $users->credentialVersion($userId);
        if ($version === null) {
            throw new UnauthorizedException('Authenticated user no longer exists.');
        }
        $this->startAuthenticatedSession($userId, $version);

        return $this->respond([
            'message' => 'Your password has been changed. Other sessions have been signed out.',
        ]);
    }

    /**
     * Send a reset link to an address if an account uses it.
     *
     * The response is identical, in content and timing, for known and unknown addresses: the
     * link is issued and sent after the response, and a delivery failure is logged without
     * detail rather than reported, so the endpoint cannot be used to discover which addresses
     * have accounts.
     */
    public function requestPasswordReset(): Response
    {
        $email = AccountTokenService::normalizeEmail((string)$this->request->getData('email', ''));
        if ($email === '') {
            throw new BadRequestException('An email address is required.');
        }

        $limiter = $this->rateLimiter();
        $client = (string)$this->request->clientIp();
        $limiter->assertAvailable('password_reset_client', $client);
        $limiter->assertAvailable('password_reset_account', $email);
        $limiter->hit('password_reset_client', $client);
        $limiter->hit('password_reset_account', $email);

        // Issuing and mailing happen only for a known address, so they run after the response
        // has been sent: the response time is then the same whether or not an account exists.
        $tokens = $this->accountTokens();
        AfterResponse::defer(static function () use ($tokens, $email): void {
            $token = $tokens->issuePasswordReset($email);
            if ($token !== null) {
                (new AccountMailer())->send('passwordReset', [
                    $email,
                    $token,
                    AccountTokenService::lifetime(AccountTokenService::PURPOSE_PASSWORD_RESET),
                ]);
            }
        });

        return $this->respond(['message' => self::RESET_REQUESTED_MESSAGE], [], 202);
    }

    /**
     * Set a new password with a reset token. The caller signs in afterwards.
     */
    public function completePasswordReset(): Response
    {
        $client = (string)$this->request->clientIp();
        $this->rateLimiter()->assertAvailable('token_redeem_client', $client);
        try {
            $user = $this->accountTokens()->redeemPasswordReset(
                (string)$this->request->getData('token', ''),
                (string)$this->request->getData('password', ''),
            );
        } catch (DomainException $exception) {
            $this->rateLimiter()->hit('token_redeem_client', $client);

            throw new BadRequestException($exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            throw new ValidationException($exception->getMessage());
        }

        return $this->respond([
            'message' => 'Your password has been changed. Sign in with the new password.',
            'user' => ['email' => (string)$user->email],
        ]);
    }

    /**
     * Load the signed-in user.
     */
    private function currentUser(): User
    {
        $userId = $this->requireUserId();

        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        /** @var \App\Model\Entity\User|null $user */
        $user = $users->find()
            ->where(['id' => $userId])
            ->first();

        if ($user === null) {
            throw new UnauthorizedException('Authenticated user no longer exists.');
        }

        return $user;
    }

    /**
     * Single source of invitation and reset tokens.
     */
    private function accountTokens(): AccountTokenService
    {
        return new AccountTokenService($this->getTableLocator());
    }

    /**
     * First validation message on the password field.
     */
    private function firstError(User $user): string
    {
        $errors = $user->getError('password');
        $first = reset($errors);

        return is_string($first) ? $first : 'invalid value.';
    }

    /**
     * Serialize a user for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => (int)$user->id,
            'email' => (string)$user->email,
            'created' => $user->created?->format(DATE_ATOM),
            'modified' => $user->modified?->format(DATE_ATOM),
        ];
    }
}
