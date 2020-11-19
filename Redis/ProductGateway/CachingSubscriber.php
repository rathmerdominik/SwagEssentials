<?php declare(strict_types=1);

namespace SwagEssentials\Redis\ProductGateway;

use Enlight\Event\SubscriberInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ProductServiceInterface;
use Shopware\Components\DependencyInjection\Container;

class CachingSubscriber implements SubscriberInterface
{
    /**
     * @var \Shopware_Components_Config
     */
    private $config;

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @param \Zend_Cache_Core $cache
     * @param ProductServiceInterface $service
     * @param int $ttl
     * @param Container $container
     * @param \Shopware_Components_Config $config
     */
    public function __construct(Container $container, \Shopware_Components_Config $config)
    {
        $this->redis = $container->get('swag_essentials.redis');
        $this->config = $config;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Article\Price::postUpdate' => 'onPostPersist',
            'Shopware\Models\Article\Price::postPersist' => 'onPostPersist',
            'Shopware\Models\Article\Article::postUpdate' => 'onPostPersist',
            'Shopware\Models\Article\Article::postPersist' => 'onPostPersist',
            'Shopware\Models\Article\Detail::postUpdate' => 'onPostPersist',
            'Shopware\Models\Article\Detail::postPersist' => 'onPostPersist',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onPostPersist(\Enlight_Event_EventArgs $eventArgs)
    {
        if (!$this->config->get('proxyPrune')) {
            return;
        }

        $entity = $eventArgs->get('entity');
        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
            $entityName = get_parent_class($entity);
        } else {
            $entityName = get_class($entity);
        }

        if (Shopware()->Events()->notifyUntil(
            'Shopware_Plugins_HttpCache_ShouldNotInvalidateCache',
            [
                'entity' => $entity,
                'entityName' => $entityName,
            ]
        )) {
            return;
        }

        $this->redis->del(ListProductService::HASH_NAME);
    }
}
