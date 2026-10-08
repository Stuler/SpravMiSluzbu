<?php declare(strict_types = 1);

namespace App\Model\Database;

use Doctrine\ORM\Decorator\EntityManagerDecorator as DoctrineEntityManagerDecorator;

class EntityManagerDecorator extends DoctrineEntityManagerDecorator
{
	private int $operationDepth = 0;
	private bool $rollbackOnly = false;
	private bool $operationFlushed = false;
	/** @var list<callable(): void> */
	private array $afterCommit = [];

	/**
	 * Nested application services join one operation; only its owner commits.
	 * @template T
	 * @param callable(self): T $func
	 * @return T
	 */
	public function wrapInTransaction(callable $func)
	{
		if ($this->operationDepth > 0) {
			$this->operationDepth++;
			try { return $func($this); }
			catch (\Throwable $e) { $this->rollbackOnly = true; throw $e; }
			finally { $this->operationDepth--; }
		}

		$connection = $this->getConnection();
		$connection->beginTransaction();
		$this->operationDepth = 1;
		$this->rollbackOnly = false;
		$this->operationFlushed = false;
		$this->afterCommit = [];
		try {
			$result = $func($this);
			$this->assertCommittable();
			$this->flush();
			$connection->commit();
			$callbacks = $this->afterCommit;
		} catch (\Throwable $e) {
			if ($connection->isTransactionActive()) { $connection->rollBack(); }
			if ($this->isOpen()) {
				// A rejected validation with no writes leaves loaded form data usable.
				// Mutated/flushed objects cannot remain authoritative after rollback.
				$unitOfWork = $this->getUnitOfWork();
				try { $unitOfWork->computeChangeSets(); }
				catch (\Throwable $cleanupError) { $this->clear(); throw $e; }
				if ($this->operationFlushed || $unitOfWork->getScheduledEntityInsertions() !== []
					|| $unitOfWork->getScheduledEntityUpdates() !== [] || $unitOfWork->getScheduledEntityDeletions() !== []
					|| $unitOfWork->getScheduledCollectionUpdates() !== [] || $unitOfWork->getScheduledCollectionDeletions() !== []) {
					$this->clear();
				}
				if ($e instanceof \Doctrine\DBAL\Exception) { $this->close(); }
			}
			throw $e;
		} finally {
			$this->operationDepth = 0;
			$this->rollbackOnly = false;
			$this->operationFlushed = false;
			$this->afterCommit = [];
		}
		// Callback failure happens after commit and must never trigger a rollback.
		foreach ($callbacks as $callback) { $callback(); }
		return $result;
	}

	/** @param callable(): void $callback */
	public function afterCommit(callable $callback): void
	{
		if ($this->operationDepth > 0) { $this->afterCommit[] = $callback; }
		else { $callback(); }
	}

	private function assertCommittable(): void
	{
		if ($this->rollbackOnly) { throw new \LogicException('Application operation is rollback-only.'); }
	}

	/** @param object|array<object>|null $entity */
	public function flush($entity = null)
	{
		if ($this->operationDepth > 0) { $this->operationFlushed = true; }
		parent::flush($entity);
	}

}
