<?php declare(strict_types=1);
namespace App\Domain\Admin;

use App\Domain\CategoryService\CategoryService;
use App\Domain\Demand\Demand;
use App\Domain\LoginRole\LoginRole;
use App\Domain\Matching\ProviderInvitation;
use App\Domain\Provider\Provider;
use App\Domain\ProviderRegion\ProviderRegion;
use App\Domain\ProviderServiceCategory\ProviderServiceCategory;
use App\Domain\Region\Region;
use App\Domain\User\User;
use App\Model\Database\EntityManagerDecorator;
use Doctrine\ORM\QueryBuilder;

final class AdminReadFacade
{
    public const PAGE_SIZE = 20;
    public function __construct(private EntityManagerDecorator $em, private AdminAuditFacade $audit) {}
    public function providers(array $filters, int $page): array
    {
        $this->audit->requireAdmin();
        $qb = $this->em->createQueryBuilder()->select('p')->from(Provider::class, 'p')->where('p.dateDeleted IS NULL');
        $this->search($qb, $filters, ['p.companyName', 'p.ico', 'p.email']);
        $this->accountState($qb, 'p', 'stateProvider', $filters);
        if (($filters['accepts'] ?? '') !== '') { $qb->andWhere('p.acceptsDemands = :accepts')->setParameter('accepts', $filters['accepts'] === '1'); }
        if (!empty($filters['category'])) {
            $qb->andWhere('EXISTS (SELECT pc.id FROM ' . ProviderServiceCategory::class . ' pc WHERE pc.provider = p AND pc.serviceCategory = :category AND pc.dateDeleted IS NULL)')
                ->setParameter('category', (int) $filters['category']);
        }
        if (!empty($filters['region'])) {
            $qb->andWhere('EXISTS (SELECT pr.id FROM ' . ProviderRegion::class . ' pr WHERE pr.provider = p AND pr.region = :region AND pr.dateDeleted IS NULL)')
                ->setParameter('region', (int) $filters['region']);
        }
        return $this->paginate($qb, 'p', $page);
    }
    public function customers(array $filters, int $page): array
    {
        $this->audit->requireAdmin();
        $qb = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->innerJoin('u.loginRole', 'role')
            ->where('u.dateDeleted IS NULL AND role.name = :role')->setParameter('role', LoginRole::ROLE_MEMBER);
        $this->search($qb, $filters, ['u.name', 'u.surname', 'u.email']);
        $this->accountState($qb, 'u', 'stateUser', $filters);
        return $this->paginate($qb, 'u', $page);
    }
    public function demands(array $filters, int $page): array
    {
        $this->audit->requireAdmin();
        return $this->paginate($this->demandQuery($filters), 'd', $page);
    }
    public function customerDemands(User $customer, int $page): array
    {
        $this->audit->requireAdmin();
        return $this->paginate($this->demandQuery([])->andWhere('d.customer = :customer')->setParameter('customer', $customer), 'd', $page);
    }
    public function providerInvitations(Provider $provider, int $page): array
    {
        $this->audit->requireAdmin();
        $qb = $this->em->createQueryBuilder()->select('i')->from(ProviderInvitation::class, 'i')->where('i.provider = :provider')->setParameter('provider', $provider);
        return $this->paginate($qb, 'i', $page);
    }
    public function overview(): array
    {
        $this->audit->requireAdmin(); $result = [];
        foreach (['review', 'no_providers', 'mail', 'responses'] as $issue) {
            $result[$issue] = (int) $this->demandQuery(['issue' => $issue])->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        }
        return $result;
    }
    public function regionOptions(): array
    {
        $this->audit->requireAdmin(); $result = [];
        foreach ($this->em->getRepository(Region::class)->findBy(['use' => true], ['name' => 'ASC']) as $region) { $result[$region->getId()] = $region->getName(); }
        return $result;
    }
    public function categories(): array
    {
        $this->audit->requireAdmin();
        return $this->em->getRepository(CategoryService::class)->findBy([], ['name' => 'ASC']);
    }
    private function demandQuery(array $filters): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()->select('d')->from(Demand::class, 'd')->innerJoin('d.city', 'city');
        $this->search($qb, $filters, ['d.reference', 'd.description', 'd.contactName', 'd.contactEmail']);
        if (!empty($filters['status'])) { $qb->andWhere('d.status = :status')->setParameter('status', $filters['status']); }
        if (!empty($filters['region'])) { $qb->andWhere('city.region = :region')->setParameter('region', (int) $filters['region']); }
        if (!empty($filters['locality'])) { $qb->andWhere('LOWER(city.fullname) LIKE :locality')->setParameter('locality', '%' . mb_strtolower($filters['locality']) . '%'); }
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            if (empty($filters[$key])) { continue; }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) { throw new \InvalidArgumentException('Invalid date filter.'); }
            $qb->andWhere('d.createdAt ' . $operator . ' :' . $key)->setParameter($key, $key === 'to' ? $date->modify('+1 day') : $date);
        }
        switch ($filters['issue'] ?? '') {
            case 'review': $qb->andWhere('d.status = :issueStatus')->setParameter('issueStatus', Demand::REVIEW); break;
            case 'no_providers': $qb->andWhere('d.status = :issueStatus')->setParameter('issueStatus', Demand::NO_PROVIDERS); break;
            case 'mail':
                $qb->andWhere('d.status <> :closed')->setParameter('closed', Demand::CLOSED)
                    ->andWhere('EXISTS (SELECT mi.id FROM ' . ProviderInvitation::class . " mi WHERE mi.demand = d AND mi.delivery IN ('unknown', 'sending'))"); break;
            case 'responses':
                $qb->andWhere('d.status = :issueStatus')->setParameter('issueStatus', Demand::INVITED)
                    ->andWhere('EXISTS (SELECT ri.id FROM ' . ProviderInvitation::class . " ri WHERE ri.demand = d AND ri.response = 'pending' AND ri.delivery = 'sent')"); break;
        }
        return $qb;
    }
    private function search(QueryBuilder $qb, array $filters, array $fields): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q === '') { return; }
        $qb->andWhere('(' . implode(' OR ', array_map(fn($field): string => 'LOWER(' . $field . ') LIKE :search', $fields)) . ')')
            ->setParameter('search', '%' . mb_strtolower(mb_substr($q, 0, 255)) . '%');
    }
    private function accountState(QueryBuilder $qb, string $alias, string $state, array $filters): void
    {
        if (($filters['state'] ?? '') === 'blocked') { $qb->andWhere("($alias.administrativelyBlocked = true OR $alias.$state = 3)"); }
        elseif (in_array($filters['state'] ?? '', ['active', 'fresh'], true)) {
            $qb->andWhere("$alias.administrativelyBlocked = false AND $alias.$state = :state")->setParameter('state', $filters['state'] === 'active' ? 2 : 1);
        }
    }
    private function paginate(QueryBuilder $qb, string $alias, int $page): array
    {
        $count = (int) (clone $qb)->select('COUNT(' . $alias . '.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($count / self::PAGE_SIZE)); $page = max(1, min($page, $pages));
        $items = $qb->orderBy($alias . '.id', 'DESC')->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();
        return ['items' => $items, 'count' => $count, 'page' => $page, 'pages' => $pages];
    }
}
