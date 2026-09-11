<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\HealthCheckService;
use Cake\Http\Response;

class HealthController extends AppController
{
    /**
     * Return API health status payload.
     */
    public function index(): Response
    {
        $health = (new HealthCheckService())->getStatus();

        return $this->respond($health);
    }
}
