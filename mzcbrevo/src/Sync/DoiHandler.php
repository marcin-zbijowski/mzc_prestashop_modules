<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Config;
use Mzc\Brevo\Queue\Result;

/**
 * Newsletter sign-up with double opt-in (job type "doi").
 *
 * Brevo sends the confirmation e-mail (the Polish DOI template configured in the
 * module) and only adds the contact to the newsletter list once the link in it is
 * clicked, then redirects to the shop's thank-you page. The attributes ride along
 * and are stored at confirmation.
 */
class DoiHandler extends AbstractHandler
{
    public const PATH = 'contacts/doubleOptinConfirmation';
    public const NOT_CONFIGURED = 'Double opt-in needs the newsletter list, the DOI template id and the redirect URL in the module settings';

    public function handle(array $jobs): array
    {
        $results = [];

        foreach ($jobs as $job) {
            $email = (string) ($job->payload['email'] ?? '');

            if ($email === '') {
                $results[$job->id] = Result::done(['skipped' => 'no e-mail']);

                continue;
            }

            $body = self::body($this->config, $email, (string) ($job->payload['source'] ?? 'formularz'));

            $results[$job->id] = $body === null
                ? Result::fail(self::NOT_CONFIGURED)
                : $this->call(fn (): Response => $this->client->post(self::PATH, $body), $body);
        }

        return $results;
    }

    /**
     * @return array<string,mixed>|null null while DOI is not fully configured
     */
    public static function body(Config $config, string $email, string $source): ?array
    {
        $list = $config->getInt(Config::LIST_NEWSLETTER);
        $template = $config->getInt(Config::DOI_TEMPLATE);
        $redirect = $config->get(Config::DOI_REDIRECT);

        if ($list <= 0 || $template <= 0 || $redirect === '') {
            return null;
        }

        return [
            'email' => $email,
            'includeListIds' => [$list],
            'templateId' => $template,
            'redirectionUrl' => $redirect,
            'attributes' => [
                'NEWSLETTER_SOURCE' => $source,
                'MARKETING_CONSENT' => true,
                'CONSENT_DATE' => date('Y-m-d'),
            ],
        ];
    }
}
