<?php declare(strict_types=1);

namespace Tests\Toolkit;

use App\Model\Database\EntityManagerDecorator;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Psr6\CacheAdapter;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;

final class IsolatedEntityManager
{
    public static function create(): EntityManagerDecorator
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../app/Domain'], true, null,
            CacheAdapter::wrap(new ArrayCache()));
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        $config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_EVAL);
        return new EntityManagerDecorator(EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config));
    }
}
