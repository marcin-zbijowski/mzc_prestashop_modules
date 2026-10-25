<?php

declare(strict_types=1);

namespace Mzc\Brevo\Command;

use Mzc\Brevo\Cart\AbandonedCartDetector;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopContext;
use Mzc\Brevo\Queue\QueueRepository;
use Mzc\Brevo\Queue\Worker;
use Mzc\Brevo\Sync\HandlerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php bin/console mzcbrevo:worker --limit=200 [--detect-abandoned]`
 *
 * Scheduled every minute in Dokploy. Sends what the hooks queued; with
 * --detect-abandoned it first looks for abandoned carts and queues their events,
 * which this same run then sends.
 */
class WorkerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('mzcbrevo:worker')
            ->setDescription('Sends queued contacts, orders, events and catalogue changes to Brevo.')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Jobs to process in this run', '200')
            ->addOption('detect-abandoned', null, InputOption::VALUE_NONE, 'Queue cart_abandoned events for abandoned carts first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\Module::isEnabled('mzcbrevo')) {
            $output->writeln('<comment>mzcbrevo is not enabled.</comment>');

            return Command::SUCCESS;
        }

        $queue = new QueueRepository();
        $factory = new HandlerFactory();

        if ($input->getOption('detect-abandoned')) {
            $detector = new AbandonedCartDetector($queue);

            foreach (\Shop::getShops(true, null, true) as $idShop) {
                $config = $factory->config((int) $idShop);

                if (!$config->isStreamEnabled(Config::STREAM_CARTS)) {
                    continue;
                }

                ShopContext::switchTo((int) $idShop);
                $found = $detector->detect($config);

                if ($found > 0 || $output->isVerbose()) {
                    $output->writeln(sprintf('Shop %d: %d abandoned cart(s) queued', $idShop, $found));
                }
            }
        }

        $worker = new Worker(
            $queue,
            [$factory, 'handler'],
            [$factory, 'config'],
            static function (string $message): void {
                \PrestaShopLogger::addLog('mzcbrevo: ' . $message, 3, null, 'mzcbrevo', 0, true);
            }
        );

        $report = $worker->run(max(1, (int) $input->getOption('limit')));
        $output->writeln($report->summary());

        foreach ($report->errors as $error) {
            $output->writeln('<error>' . $error . '</error>');
        }

        return Command::SUCCESS;
    }
}
