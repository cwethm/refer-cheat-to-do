<?php
declare(strict_types=1);

namespace App\Controller\Api;

use Cake\Auth\DefaultPasswordHasher;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\FactoryLocator;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\Query\SelectQuery;

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
        $user = $users->find()
            ->select(['id', 'email', 'password', 'created', 'modified'])
            ->where(function (QueryExpression $exp, SelectQuery $query) use ($email) {
                return $exp->eq($query->func()->lower(['email' => 'identifier']), $email);
            })
            ->disableHydration()
            ->first();
        if (!is_array($user)) {
            throw new UnauthorizedException('Invalid credentials.');
        }

        if (!(new DefaultPasswordHasher())->check($password, (string)$user['password'])) {
            throw new UnauthorizedException('Invalid credentials.');
        }

        $session = $this->request->getSession();
        $session->renew();
        $session->write('Auth.user_id', (int)$user['id']);
        $session->write('Auth.identity', [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'created' => $user['created'],
            'modified' => $user['modified'],
        ]);

        return $this->respond([
            'user' => [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            ],
        ]);
    }

    public function logout()
    {
        $this->requireAuthenticatedIdentity();
        $session = $this->request->getSession();
        $session->delete('Auth.user_id');
        $session->delete('Auth.identity');
        $session->renew();

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
