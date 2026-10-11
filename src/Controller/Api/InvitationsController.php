<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Mailer\AccountMailer;
use App\Service\AccountTokenService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ServiceUnavailableException;
use Cake\Http\Response;
use Cake\Validation\Validation;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Invitation-based account creation (Slice 19).
 *
 * Any signed-in account may invite an address; there is no public registration. The link is
 * emailed to the invited address only and never returned to the issuer, so an account can only
 * be created by someone who controls the address it is created for.
 */
class InvitationsController extends AppController
{
    /**
     * Response for every accepted invitation, whether or not the address already has an account.
     */
    public const INVITED_MESSAGE =
        'If that address does not already have an account, an invitation has been sent to it.';

    /**
     * Invite an address to create an account.
     */
    public function add(): Response
    {
        $issuerId = $this->requireUserId();
        $email = AccountTokenService::normalizeEmail((string)$this->request->getData('email', ''));
        if ($email === '' || mb_strlen($email) > 191 || !Validation::email($email)) {
            throw new ValidationException('Enter a valid email address to invite.');
        }

        $limiter = $this->rateLimiter();
        $limiter->assertAvailable('invitation_issue_account', (string)$issuerId);
        $limiter->hit('invitation_issue_account', (string)$issuerId);

        $tokens = new AccountTokenService($this->getTableLocator());
        $token = $tokens->issueInvitation($issuerId, $email);
        if ($token !== null) {
            try {
                (new AccountMailer())->send('invitation', [
                    $email,
                    $token,
                    AccountTokenService::lifetime(AccountTokenService::PURPOSE_INVITATION),
                ]);
            } catch (Throwable) {
                throw new ServiceUnavailableException('The invitation could not be sent. Please try again later.');
            }
        }

        return $this->respond(['message' => self::INVITED_MESSAGE], [], 202);
    }

    /**
     * Create the invited account with a chosen password. The new user signs in afterwards.
     */
    public function accept(): Response
    {
        $client = (string)$this->request->clientIp();
        $this->rateLimiter()->assertAvailable('token_redeem_client', $client);
        try {
            $user = (new AccountTokenService($this->getTableLocator()))->redeemInvitation(
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
            'message' => 'Your account is ready. Sign in with your new password.',
            'user' => ['email' => (string)$user->email],
        ], [], 201);
    }
}
