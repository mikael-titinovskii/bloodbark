<?php

namespace App\Command;

use App\Service\Analytics\AnalyticsRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analytics:purge', description: 'Delete analytics data past its retention period.')]
final class PurgeAnalyticsCommand extends Command
{
    public function __construct(private readonly AnalyticsRetention $retention)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->retention->purge();

        (new SymfonyStyle($input, $output))->success(sprintf(
            'Deleted %d analytics events and %d expired consent records.',
            $deleted['events'],
            $deleted['consents'],
        ));

        return Command::SUCCESS;
    }
}
