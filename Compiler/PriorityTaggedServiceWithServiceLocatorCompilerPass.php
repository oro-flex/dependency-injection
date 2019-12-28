<?php

namespace Oro\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Finds all services with the given tag name, orders them by their priority
 * and adds an array of items returned by the attributes handler and a service locator contains them
 * to the definition of the given service.
 */
class PriorityTaggedServiceWithServiceLocatorCompilerPass implements CompilerPassInterface
{
    use TaggedServiceTrait;

    /** @var string */
    private $serviceId;

    /** @var string */
    private $tagName;

    /** @var \Closure */
    private $attributesHandler;

    /** @var bool */
    private $isServiceOptional;

    /**
     * @param string   $serviceId
     * @param string   $tagName
     * @param \Closure $attributesHandler function (array $attributes, string $serviceId, string $tagName): array
     * @param bool     $isServiceOptional
     */
    public function __construct(
        string $serviceId,
        string $tagName,
        \Closure $attributesHandler,
        bool $isServiceOptional = false
    ) {
        $this->serviceId = $serviceId;
        $this->tagName = $tagName;
        $this->attributesHandler = $attributesHandler;
        $this->isServiceOptional = $isServiceOptional;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($this->isServiceOptional && !$container->hasDefinition($this->serviceId)) {
            return;
        }

        $services = [];
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($this->tagName);
        foreach ($taggedServices as $serviceId => $tags) {
            $services[$serviceId] = new Reference($serviceId);
            foreach ($tags as $attributes) {
                $items[$this->getPriorityAttribute($attributes)][] = \call_user_func(
                    $this->attributesHandler,
                    $attributes,
                    $serviceId,
                    $this->tagName
                );
            }
        }
        if ($items) {
            $items = $this->sortByPriorityAndFlatten($items);
        }

        $container->getDefinition($this->serviceId)
            ->setArgument(0, $items)
            ->setArgument(1, ServiceLocatorTagPass::register($container, $services));
    }
}
