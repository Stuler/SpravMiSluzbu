<?php declare(strict_types=1);

namespace App\Console;

use App\Domain\Matching\MatchingFacade;
use App\Domain\Matching\StructuredAiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'demands:evaluate')]
final class DemandEvaluateCommand extends Command
{
	public function __construct(private StructuredAiClient $ai, private string $rootDirectory) { parent::__construct(); }
	protected function configure(): void
	{
		$this->setDescription('Evaluate the analysis prompt against synthetic Slovak fixtures without database writes or email.')
			->addOption('live', null, InputOption::VALUE_NONE, 'Allow paid OpenAI API requests for every fixture');
	}
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		if (!$input->getOption('live')) {
			$output->writeln('No API requests made. Pass --live to evaluate using configured OpenAI credentials.');
			return Command::SUCCESS;
		}
		$fixtures = json_decode(file_get_contents($this->rootDirectory . '/tests/Fixtures/Matching/analysis-cases.json'), true, 64, JSON_THROW_ON_ERROR);
		$prompt = file_get_contents($this->rootDirectory . '/resources/prompts/demand-analysis-v1.txt');
		$failed = 0;
		foreach ($fixtures['cases'] as $case) {
			try {
				$result = $this->ai->complete($prompt, ['description' => $case['description'], 'term' => 'Podľa dohody',
					'selected_category_id' => $case['hint'], 'catalogue' => $fixtures['catalogue']], MatchingFacade::analysisSchema());
				$data = $result['data'];
				$ids = $data['category_ids'] ?? [];
				sort($ids);
				$passed = ($data['needs_review'] ?? null) === $case['review'] && ($case['review'] || $ids === $case['expected']);
			} catch (\Throwable $e) { $passed = false; }
			$output->writeln(($passed ? 'PASS ' : 'FAIL ') . $case['id']);
			$failed += $passed ? 0 : 1;
		}
		$output->writeln(sprintf('%d/%d cases passed.', count($fixtures['cases']) - $failed, count($fixtures['cases'])));
		return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
	}
}
