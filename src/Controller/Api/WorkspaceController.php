<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Policy\OwnershipPolicy;
use Cake\Http\Exception\ForbiddenException;

class WorkspaceController extends AppController
{
    public function index()
    {
        $identity = $this->requireAuthenticatedIdentity();

        return $this->respond([
            'workspace' => [
                'user_id' => (int)$identity['id'],
                'email' => (string)$identity['email'],
            ],
        ]);
    }

    public function view(int $userId)
    {
        $identity = $this->requireAuthenticatedIdentity();
        $policy = new OwnershipPolicy();

        if (!$policy->canAccess((int)$identity['id'], $userId)) {
            throw new ForbiddenException('You cannot access another user workspace.');
        }

        return $this->respond([
            'workspace' => [
                'user_id' => $userId,
                'email' => (string)$identity['email'],
            ],
        ]);
    }
}
