<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\DashboardService;
use App\Service\OrphanDetectionService;
use App\Service\ReviewSchedulingService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;

class DashboardController extends AppController
{
    /**
     * Summarize what requires the current user's attention.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $recentLimit = $this->readRecentLimit();

        return $this->respond($this->dashboard()->summarize($userId, $recentLimit));
    }

    /**
     * Build the dashboard summary authority from the existing domain services.
     */
    private function dashboard(): DashboardService
    {
        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return new DashboardService(
            $this->getTableLocator(),
            new OrphanDetectionService(),
            $this->activity(),
            new ReviewSchedulingService($todosTable),
        );
    }

    /**
     * Read and validate the optional `recent_limit` query parameter.
     */
    private function readRecentLimit(): int
    {
        $value = $this->request->getQuery('recent_limit');
        if ($value === null || $value === '') {
            return DashboardService::DEFAULT_RECENT_LIMIT;
        }
        if (!is_string($value) || !ctype_digit($value) || (int)$value < 1) {
            throw new BadRequestException('Invalid query parameter `recent_limit`.');
        }

        return min((int)$value, DashboardService::MAX_RECENT_LIMIT);
    }
}
