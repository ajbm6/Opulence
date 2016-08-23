<?php
/**
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2016 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */
namespace Opulence\Ioc\Bootstrappers\Dispatchers;

use Opulence\Ioc\Bootstrappers\Bootstrapper;
use Opulence\Ioc\Bootstrappers\Caching\ICache;
use Opulence\Ioc\Bootstrappers\IBootstrapperRegistry;
use Opulence\Ioc\IContainer;
use RuntimeException;

/**
 * Defines the bootstrapper dispatcher
 */
class BootstrapperDispatcher implements IBootstrapperDispatcher
{
    /** @var IContainer The IoC container */
    private $container = null;
    /** @var IBootstrapperRegistry The bootstrapper registry */
    private $bootstrapperRegistry = null;
    /** @var ICache The bootstrapper cache */
    private $bootstrapperCache = null;
    /** @var string The path to the cached registry */
    private $cachedRegistryPath = "";
    /** @var array The list of bootstrapper classes that have been run */
    private $runBootstrappers = [];
    /** @var Bootstrapper[] The list of instantiated bootstrappers */
    private $bootstrapperObjects = [];

    /**
     * @param IContainer $container The IoC container
     * @param IBootstrapperRegistry $bootstrapperRegistry The bootstrapper registry
     * @param ICache $bootstrapperCache The bootstrapper cache
     * @param string $cachedRegistryPath The path to the cached registry
     */
    public function __construct(
        IContainer $container,
        IBootstrapperRegistry $bootstrapperRegistry,
        ICache $bootstrapperCache = null,
        string $cachedRegistryPath = ""
    ) {
        $this->container = $container;
        $this->bootstrapperRegistry = $bootstrapperRegistry;
        $this->bootstrapperCache = $bootstrapperCache;
        $this->cachedRegistryPath = $cachedRegistryPath;
    }

    /**
     * @inheritdoc
     */
    public function shutDownBootstrappers()
    {
        foreach ($this->bootstrapperObjects as $bootstrapper) {
            $this->container->callMethod($bootstrapper, "shutdown", [], true);
        }
    }

    /**
     * @inheritdoc
     */
    public function startBootstrappers(bool $forceEagerLoading, bool $useCache)
    {
        if ($useCache && !empty($this->cachedRegistryPath)) {
            $this->bootstrapperCache->get($this->cachedRegistryPath, $this->bootstrapperRegistry);
        } else {
            $this->bootstrapperRegistry->setBootstrapperDetails();
        }

        if ($forceEagerLoading) {
            $eagerBootstrapperClasses = $this->bootstrapperRegistry->getEagerBootstrappers();
            $lazyBootstrapperClasses = [];

            foreach (array_values($this->bootstrapperRegistry->getLazyBootstrapperBindings()) as $bindingData) {
                $lazyBootstrapperClasses[] = $bindingData["bootstrapper"];
            }

            $lazyBootstrapperClasses = array_unique($lazyBootstrapperClasses);
            $bootstrapperClasses = array_merge($eagerBootstrapperClasses, $lazyBootstrapperClasses);
            $this->dispatchEagerly($bootstrapperClasses);
        } else {
            // We must dispatch lazy bootstrappers first in case their bindings are used by eager bootstrappers
            $this->dispatchLazily($this->bootstrapperRegistry->getLazyBootstrapperBindings());
            $this->dispatchEagerly($this->bootstrapperRegistry->getEagerBootstrappers());
        }
    }

    /**
     * Dispatches the registry eagerly
     *
     * @param array $bootstrapperClasses The list of bootstrapper classes to dispatch
     * @throws RuntimeException Thrown if there was a problem dispatching the bootstrappers
     */
    private function dispatchEagerly(array $bootstrapperClasses)
    {
        foreach ($bootstrapperClasses as $bootstrapperClass) {
            /** @var Bootstrapper $bootstrapper */
            $bootstrapper = $this->bootstrapperRegistry->resolve($bootstrapperClass);
            $bootstrapper->initialize();
            $this->bootstrapperObjects[] = $bootstrapper;
        }

        foreach ($this->bootstrapperObjects as $bootstrapper) {
            $bootstrapper->registerBindings($this->container);
        }

        foreach ($this->bootstrapperObjects as $bootstrapper) {
            $this->container->callMethod($bootstrapper, "run", [], true);
        }
    }

    /**
     * Dispatches the registry lazily
     *
     * @param array $boundClassesToBindingData The mapping of bound classes to their targets and bootstrappers
     * @throws RuntimeException Thrown if there was a problem dispatching the bootstrappers
     */
    private function dispatchLazily(array $boundClassesToBindingData)
    {
        foreach ($boundClassesToBindingData as $boundClass => $bindingData) {
            $bootstrapperClass = $bindingData["bootstrapper"];
            $target = $bindingData["target"];

            $factory = function () use ($boundClass, $bootstrapperClass, $target) {
                // To make sure this factory isn't used anymore to resolve the bound class, unbind it
                // Otherwise, we'd get into an infinite loop every time we tried to resolve it
                if ($target === null) {
                    $this->container->unbind($boundClass);
                } else {
                    $this->container->for($target, function (IContainer $container) use ($boundClass) {
                        $container->unbind($boundClass);
                    });
                }

                $bootstrapper = $this->bootstrapperRegistry->resolve($bootstrapperClass);

                if (!in_array($bootstrapper, $this->bootstrapperObjects)) {
                    $this->bootstrapperObjects[] = $bootstrapper;
                }

                if (!isset($this->runBootstrappers[$bootstrapperClass])) {
                    $bootstrapper->initialize();
                    $bootstrapper->registerBindings($this->container);
                    $this->container->callMethod($bootstrapper, "run", [], true);
                    $this->runBootstrappers[$bootstrapperClass] = true;
                }

                if ($target === null) {
                    return $this->container->resolve($boundClass);
                } else {
                    return $this->container->for($target, function (IContainer $container) use ($boundClass) {
                        return $container->resolve($boundClass);
                    });
                }
            };

            if ($target === null) {
                $this->container->bindFactory($boundClass, $factory);
            } else {
                $this->container->for($target, function (IContainer $container) use ($boundClass, $factory) {
                    $container->bindFactory($boundClass, $factory);
                });
            }
        }
    }
}