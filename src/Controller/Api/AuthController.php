<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\User;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;

class AuthController extends AppController
{
    /**
     * Authenticate with email and password and create session context.
     */
    public function login(): Response
    {
        $email = strtolower(trim((string)$this->request->getData('email', '')));
        $password = (string)$this->request->getData('password', '');

        if ($email === '' || $password === '') {
            throw new BadRequestException('Email and password are required.');
        }

        /** @var \App\Model\Table\UsersTable $users */
        $users = $this->fetchTable('Users');
        /** @var \App\Model\Entity\User|null $user */
        $user = $users->find()
            ->where(['email' => $email])
            ->first();

        if ($user === null || !password_verify($password, (string)$user->password)) {
            throw new UnauthorizedException('Invalid credentials.');
        }

        $this->request->getSession()->write('Auth.user_id', (int)$user->id);

        return $this->respond([
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Remove authenticated user context from session.
     */
    public function logout(): Response
    {
        $this->request->getSession()->delete('Auth.user_id');

        return $this->respond([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Return the current authenticated user.
     */
    public function me(): Response
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

        return $this->respond([
            'user' => $this->serializeUser($user),
        ]);
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
