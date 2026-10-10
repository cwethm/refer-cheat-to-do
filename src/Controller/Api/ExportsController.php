<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\DataPortabilityService;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Response;
use Laminas\Diactoros\Stream;
use Throwable;

class ExportsController extends AppController
{
    /**
     * Download only the signed-in account's workspace, never a requested user id.
     */
    public function download(): Response
    {
        $this->request->allowMethod(['get']);
        $userId = $this->requireUserId();
        $file = tmpfile();
        if ($file === false) {
            throw new InternalErrorException('The export could not be prepared.');
        }

        try {
            (new DataPortabilityService($this->getTableLocator()))->writeExport($userId, $file);
            rewind($file);

            return $this->getResponse()
                ->withHeader('Content-Type', 'application/x-ndjson')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withDownload('cheat-to-do-export-v1.jsonl')
                ->withBody(new Stream($file));
        } catch (Throwable $exception) {
            fclose($file);
            throw $exception;
        }
    }
}
