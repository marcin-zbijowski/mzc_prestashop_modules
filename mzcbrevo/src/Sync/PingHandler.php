<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\Response;

/**
 * The back office's "queue a test job" (job type "ping"): GET /v3/account through the
 * whole queue → worker → Brevo path, retries included. Sends no data.
 */
class PingHandler extends AbstractHandler
{
    public function handle(array $jobs): array
    {
        $results = [];
        $result = $this->call(fn (): Response => $this->client->get('account'), ['GET' => 'account']);

        foreach ($jobs as $job) {
            $results[$job->id] = $result;
        }

        return $results;
    }
}
