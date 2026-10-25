<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Queue\Result;

/**
 * Right to be forgotten (job type "delete"): a customer deleted in the shop is
 * deleted in Brevo. The e-mail is captured when the hook fires — by the time the
 * worker runs, the customer row is gone.
 */
class DeleteContactHandler extends AbstractHandler
{
    public function handle(array $jobs): array
    {
        $results = [];

        foreach ($jobs as $job) {
            $email = (string) ($job->payload['email'] ?? '');

            if ($email === '') {
                $results[$job->id] = Result::done(['skipped' => 'no e-mail']);

                continue;
            }

            $response = $this->raw(fn (): Response => $this->client->delete(BrevoClient::contactPath($email), ['identifierType' => 'email_id']));

            if ($response instanceof Result) {
                $results[$job->id] = $response;

                continue;
            }

            // 404: never synced, or already gone — the state we want either way.
            $results[$job->id] = $response->isSuccess() || $response->status === 404
                ? Result::done(['deleted' => $email])
                : $this->interpret($response);
        }

        return $results;
    }
}
