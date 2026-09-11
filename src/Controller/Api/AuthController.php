<?php
declare(strict_types=1);

namespace App\Controller\Api;

use Cake\Auth\DefaultPasswordHasher;
use Cake\Datasource\FactoryLocator;
use Cake\Http\Exception\UnauthorizedException;

class AuthController extends AppController
{
    public function login()
    {
        $email = mb_strtolower(trim((string)$this->request->getData('email')));
        $password = (string)$this->request->getData('password');

        $errors = [];
        if ($email === '') {
            $errors['email'][] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }
        if ($errors !== []) {
            return $this->respondError('VALIDATION_ERROR', 'The submitted data is invalid.', $errors, 422);
        }

        $users = FactoryLocator::get('Table')->get('Users');
        $user = $users->find()->where(['email' => $email])->first();

        if ($user === null || !(new DefaultPasswordHasher())->check($password, (string)$user->get('password'))) {
            throw new UnauthorizedException('Invalid credentials.');
        }

        $this->request->getSession()->renew();
        $this->request->getSession()->write('Auth.user_id', (int)$user->get('id'));

        return $this->respond([
            'user' => [
                'id' => (int)$user->get('id'),
                'email' => (string)$user->get('email'),
            ],
        ]);
    }

    public function logout()
    {
        $this->requireAuthenticatedIdentity();
        $this->request->getSession()->delete('Auth.user_id');
        $this->request->getSession()->renew();

        return $this->respond(['message' => 'Logged out.']);
    }

    public function me()
    {
        $identity = $this->requireAuthenticatedIdentity();

        return $this->respond([
            'user' => [
                'id' => (int)$identity['id'],
                'email' => (string)$identity['email'],
                'created' => $identity['created'],
                'modified' => $identity['modified'],
            ],
        ]);
    }
}
