<?php declare(strict_types=1);

namespace App\Console;

use App\Domain\Matching\MatchingFacade;
use App\Domain\Matching\InvitationFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'demands:work')]
final class DemandWorkerCommand extends Command
{
	public function __construct(private MatchingFacade $matching, private InvitationFacade $invitations) { parent::__construct(); }
	protected function configure(): void
	{
		$this->setDescription('Process verified demands and optionally deliver provider invitation emails.')
			->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum jobs per run (1-50)', '10')
			->addOption('send', null, InputOption::VALUE_NONE, 'Deliver queued invitation emails as well');
	}
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$limit = max(1, min(50, (int) $input->getOption('limit')));
		$processed = 0;
		for ($i = 0; $i < $limit && $this->matching->processOne(); $i++) { $processed++; }
		$delivered = 0;
		if ($input->getOption('send')) {
			for ($i = 0; $i < $limit && $this->invitations->deliverOne(); $i++) { $delivered++; }
		}
		$output->writeln(sprintf('Processed demands: %d; invitation delivery attempts: %d.', $processed, $delivered));
		return Command::SUCCESS;
	}
}
