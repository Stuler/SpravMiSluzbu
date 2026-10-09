<?php declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\CategoryService\CategoryService;
use App\Domain\Demand\Demand;
use App\Domain\Provider\Provider;
use App\Model\Database\EntityManagerDecorator;

final class MatchingFacade
{
	public const INVITATION_LIMIT = 3;
	public function __construct(private EntityManagerDecorator $em, private StructuredAiClient $ai, private string $promptDirectory,
		private \App\Domain\Demand\DemandRepository $demands,
		private \App\Domain\Provider\ProviderRepository $providers, private InvitationRepository $invitationRepository) {}

	/** @param int[] $categoryIds @return Provider[] */
	public function eligibleProviders(Demand $demand, array $categoryIds): array
	{
		if ($categoryIds === []) { return []; }
		$categoryIds = array_values(array_filter($categoryIds, function ($id): bool {
			$category = $this->em->find(CategoryService::class, $id);
			return $category instanceof CategoryService && $category->isSelectable();
		}));
		if ($categoryIds === []) { return []; }
		// Exact category matching is deliberate; a broad category does not imply every specialization.
		return $this->providers->eligibleForDemand($demand, $categoryIds);
	}

	public function processOne(): bool
	{
		$claim = $this->em->wrapInTransaction(function (): ?array {
			$now = new \DateTimeImmutable();
			$demand = $this->demands->nextReadyForUpdate($now);
			if ($demand === null) { return null; }
			return [$demand, $demand->claimProcessing($now)];
		});
		if ($claim === null) { return false; }
		[$demand, $lease] = $claim;
		try {
			if ($demand->getProcessingAttempts() > 3) { throw new AiUnavailableException('Processing attempt limit exceeded.'); }
			$result = $this->preview($demand, false);
			$this->em->wrapInTransaction(function () use ($demand, $lease, $result): void {
				$this->demands->refreshForUpdate($demand);
				if (!$demand->ownsLease($lease)) { return; }
				$this->applySelection($demand, $result);
			});
		} catch (\Throwable $e) {
			if (!$this->em->isOpen()) { throw $e; }
			$demand = $this->demands->findByReference($demand->getReference());
			if ($demand === null) { throw $e; }
			$this->em->wrapInTransaction(function () use ($demand, $lease, $e): void {
				$this->demands->refreshForUpdate($demand);
				if (!$demand->ownsLease($lease)) { return; }
				$review = $e instanceof AiUnavailableException || $demand->getProcessingAttempts() >= 3;
				if ($review) { $demand->markNeedsReview('Automatické spracovanie vyžaduje kontrolu administrátora.'); }
				else { $demand->scheduleRetry('Spracovanie sa dočasne nepodarilo. Pokus zopakujeme.'); }
			});
		}
		return true;
	}

	/** A preview records AI runs, but never sends messages or changes the demand state. */
	public function preview(Demand $demand, bool $dryRun = true): array
	{
		$catalogue = [];
		foreach ($this->em->getRepository(CategoryService::class)->findBy(['dateDeleted' => null], ['id' => 'ASC']) as $category) {
			if (!$category->isSelectable()) { continue; }
			$catalogue[] = ['id' => $category->getId(), 'name' => $category->getName(), 'parent_id' => $category->getParent()?->getId()];
		}
		$input = ['description' => $this->redact($demand->getDescription(), $demand), 'term' => $this->redact($demand->getRequestedTerm(), $demand),
			'selected_category_id' => $demand->getCategory()?->getId(), 'catalogue' => $catalogue];
		$analysis = $this->call($demand, 'demand-analysis-v1', $input, self::analysisSchema(), $dryRun);
		$ids = $analysis['category_ids'] ?? null;
		if (!is_array($ids) || count($ids) > 5 || count(array_filter($ids, 'is_int')) !== count($ids) || count(array_unique($ids)) !== count($ids)
			|| !is_bool($analysis['needs_review'] ?? null) || !is_string($analysis['summary'] ?? null)
			|| !is_string($analysis['review_reason'] ?? null) || mb_strlen($analysis['summary']) > 2000) {
			throw new \UnexpectedValueException('Invalid analysis output.');
		}
		foreach ($ids as $id) {
			if (!is_int($id) || !in_array($id, array_column($catalogue, 'id'), true)) { throw new \UnexpectedValueException('Unknown AI category.'); }
		}
		$summary = $this->redact($analysis['summary'], $demand);
		if ($analysis['needs_review'] || count($ids) !== 1 || trim($summary) === '') {
			return ['status' => Demand::REVIEW, 'note' => $this->redact($analysis['review_reason'], $demand), 'summary' => $summary, 'category_ids' => $ids, 'selection' => []];
		}
		$candidates = $this->eligibleProviders($demand, $ids);
		if ($candidates === []) {
			return ['status' => Demand::NO_PROVIDERS, 'note' => 'V regióne nie je vhodný aktívny poskytovateľ.', 'summary' => $summary, 'category_ids' => $ids, 'selection' => []];
		}
		$rankInput = ['request' => $summary, 'candidates' => array_map(fn(Provider $p): array => [
			'id' => $p->getId(), 'services' => $this->redact($p->getServiceDescription() ?? '', $demand),
		], $candidates)];
		$ranking = $this->call($demand, 'provider-ranking-v1', $rankInput, self::rankingSchema(), $dryRun);
		$selection = $ranking['selection'] ?? null;
		if (!is_array($selection) || count($selection) > self::INVITATION_LIMIT) { throw new \UnexpectedValueException('Invalid ranking.'); }
		$seen = [];
		$allowed = array_map(fn(Provider $p): int => $p->getId(), $candidates);
		foreach ($selection as $row) {
			$id = $row['provider_id'] ?? null;
			if (!is_int($id) || !in_array($id, $allowed, true) || in_array($id, $seen, true) || !is_string($row['reason'] ?? null)
				|| mb_strlen($row['reason']) > 500) { throw new \UnexpectedValueException('Invalid provider selection.'); }
			$seen[] = $id;
		}
		return ['status' => $selection === [] ? Demand::REVIEW : Demand::INVITED, 'note' => $selection === [] ? 'AI nevybrala vhodného kandidáta.' : 'Výber poskytovateľov dokončený.',
			'summary' => $summary, 'category_ids' => $ids, 'selection' => $selection];
	}

	/** @return Demand[] */
	public function recentDemands(): array { return $this->em->getRepository(Demand::class)->findBy([], ['createdAt' => 'DESC'], 100); }
	public function runs(Demand $demand): array { return $this->em->getRepository(AiRun::class)->findBy(['demand' => $demand], ['createdAt' => 'DESC'], 20); }
	public function invitations(Demand $demand): array { return $this->invitationRepository->forDemand($demand); }

	public function requeue(Demand $demand, ?int $categoryId): void
	{
		$category = $categoryId ? $this->em->find(CategoryService::class, $categoryId) : null;
		if ($categoryId && (!$category instanceof CategoryService || !$category->isSelectable())) { throw new \InvalidArgumentException('Unknown category.'); }
		$requeued = $this->em->wrapInTransaction(function () use ($demand, $category): bool {
			$this->demands->refreshForUpdate($demand);
			if ($demand->isMatchingStopped() || $demand->getVerifiedAt() === null || $demand->getStatus() === Demand::CLOSED) { return false; }
			$demand->requeue($category);
			return true;
		});
		if (!$requeued) { throw new \InvalidArgumentException('Demand is not open and verified.'); }
	}

	public function approveManually(Demand $demand, array $providerIds): void
	{
		if ($demand->getCategory() === null || $providerIds === [] || count($providerIds) > self::INVITATION_LIMIT) {
			throw new \InvalidArgumentException('A category and up to three providers are required.');
		}
		$approved = $this->em->wrapInTransaction(function () use ($demand, $providerIds): bool {
			$this->demands->refreshForUpdate($demand);
			if ($demand->isMatchingStopped() || $demand->getVerifiedAt() === null || $demand->getStatus() === Demand::CLOSED || $demand->getCategory() === null) { return false; }
			$this->applySelection($demand, ['status' => Demand::INVITED, 'note' => 'Poskytovateľov vybral administrátor.',
				'summary' => $demand->getServiceSummary() ?: $demand->getCategory()->getName() . '. Zadanie skontroloval administrátor.',
				'category_ids' => [$demand->getCategory()->getId()], 'selection' => array_map(fn($id): array => ['provider_id' => (int) $id, 'reason' => 'Vybral administrátor.'], $providerIds)]);
			return true;
		});
		if (!$approved) { throw new \InvalidArgumentException('Demand is not open and verified.'); }
	}

	private function applySelection(Demand $demand, array $result): void
	{
		if ($demand->isMatchingStopped()) { throw new \InvalidArgumentException('Matching stopped after provider selection.'); }
		$existing = $this->invitations($demand);
		$existingIds = array_map(fn(ProviderInvitation $i): int => $i->getProvider()->getId(), $existing);
		$eligible = [];
		foreach ($this->eligibleProviders($demand, $result['category_ids']) as $provider) { $eligible[$provider->getId()] = $provider; }
		$count = count($existing);
		foreach ($result['selection'] as $row) {
			$id = $row['provider_id'];
			if ($count >= self::INVITATION_LIMIT) { break; }
			if (!isset($eligible[$id]) || in_array($id, $existingIds, true)) { continue; }
			$this->em->persist(new ProviderInvitation($demand, $eligible[$id], $this->redact($row['reason'], $demand)));
			$existingIds[] = $id;
			$count++;
		}
		$status = $count > 0 ? Demand::INVITED : ($result['status'] === Demand::INVITED ? Demand::REVIEW : $result['status']);
		$note = $result['note'] ?: 'Zadanie vyžaduje kontrolu.';
		match ($status) {
			Demand::REVIEW => $demand->markNeedsReview($note, $result['summary']),
			Demand::NO_PROVIDERS => $demand->markNoProviders($note, $result['summary']),
			Demand::INVITED => $demand->markProvidersInvited($note, $result['summary']),
			default => throw new \InvalidArgumentException('Invalid matching outcome.'),
		};
	}

	private function call(Demand $demand, string $version, array $input, array $schema, bool $dryRun): array
	{
		$prompt = file_get_contents($this->promptDirectory . '/' . $version . '.txt');
		if ($prompt === false) { throw new AiUnavailableException('Prompt file is missing.'); }
		$result = $this->ai->complete($prompt, $input, $schema);
		$this->em->persist(new AiRun($demand, $version . ':' . substr(hash('sha256', $prompt . json_encode($schema, JSON_THROW_ON_ERROR)), 0, 12) . ($dryRun ? ':preview' : ''),
			$result['model'], $input, $result['data'], $result['inputTokens'], $result['outputTokens'], $result['durationMs']));
		$this->em->flush();
		return $result['data'];
	}

	public function redact(string $text, Demand $demand): string
	{
		foreach ([$demand->getContactEmail(), $demand->getContactPhone(), $demand->getContactName()] as $value) {
			if ($value !== null && mb_strlen($value) >= 3) { $text = str_ireplace($value, '[kontakt]', $text); }
		}
		$text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[e-mail]', $text) ?? '';
		return preg_replace('/(?<!\d)(?:\+?\d[\s().-]*){9,15}(?!\d)/u', '[telefón]', $text) ?? '';
	}

	public static function analysisSchema(): array
	{
		return ['type' => 'object', 'properties' => ['category_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
			'needs_review' => ['type' => 'boolean'], 'summary' => ['type' => 'string'], 'review_reason' => ['type' => 'string']],
			'required' => ['category_ids', 'needs_review', 'summary', 'review_reason'], 'additionalProperties' => false];
	}
	public static function rankingSchema(): array
	{
		return ['type' => 'object', 'properties' => ['selection' => ['type' => 'array', 'items' => ['type' => 'object',
			'properties' => ['provider_id' => ['type' => 'integer'], 'reason' => ['type' => 'string']],
			'required' => ['provider_id', 'reason'], 'additionalProperties' => false]]], 'required' => ['selection'], 'additionalProperties' => false];
	}
}
