<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

class HealthCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'status' => 'ok',
            'application' => [
                'name' => (string)Configure::read('App.name'),
                'environment' => (string)Configure::read('App.env'),
                'debug' => (bool)Configure::read('debug'),
            ],
        ];
    }
}
