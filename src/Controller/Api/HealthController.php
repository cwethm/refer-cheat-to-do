<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\HealthCheckService;

class HealthController extends AppController
{
    public function index()
    {
        $health = (new HealthCheckService())->getStatus();

        return $this->respond($health);
    }
}
