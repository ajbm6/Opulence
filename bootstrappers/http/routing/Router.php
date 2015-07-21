<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines the routing bootstrapper
 */
namespace Opulence\Framework\Bootstrappers\HTTP\Routing;
use Opulence\Applications\Bootstrappers\Bootstrapper;
use Opulence\IoC\IContainer;
use Opulence\Routing\Dispatchers\Dispatcher;
use Opulence\Routing\Dispatchers\IDispatcher;
use Opulence\Routing\Router as HTTPRouter;
use Opulence\Routing\Routes\Caching\Cache;
use Opulence\Routing\Routes\Caching\ICache;
use Opulence\Routing\Routes\Compilers\Compiler;
use Opulence\Routing\Routes\Compilers\ICompiler;
use Opulence\Routing\Routes\Compilers\Matchers\HostMatcher;
use Opulence\Routing\Routes\Compilers\Matchers\IRouteMatcher;
use Opulence\Routing\Routes\Compilers\Matchers\PathMatcher;
use Opulence\Routing\Routes\Compilers\Matchers\SchemeMatcher;
use Opulence\Routing\Routes\Compilers\Parsers\IParser;
use Opulence\Routing\Routes\Compilers\Parsers\Parser;
use Opulence\Routing\URL\URLGenerator;

class Router extends Bootstrapper
{
    /** @var ICache The route cache */
    protected $cache = null;
    /** @var IParser The route parser */
    protected $parser = null;

    /**
     * @inheritdoc
     */
    public function registerBindings(IContainer $container)
    {
        $this->cache = $this->getRouteCache($container);
        $dispatcher = $this->getRouteDispatcher($container);
        $this->parser = $this->getRouteParser($container);
        $compiler = $this->getRouteCompiler($container);
        $router = new HTTPRouter($dispatcher, $compiler, $this->parser);
        $this->configureRouter($router);
        $urlGenerator = new URLGenerator($router->getRouteCollection(), $this->parser->getVariableMatchingRegex());
        $container->bind(ICache::class, $this->cache);
        $container->bind(IDispatcher::class, $dispatcher);
        $container->bind(ICompiler::class, $compiler);
        $container->bind(HTTPRouter::class, $router);
        $container->bind(URLGenerator::class, $urlGenerator);
    }

    /**
     * Configures the router, which is useful for things like caching
     *
     * @param HTTPRouter $router The router to configure
     */
    protected function configureRouter(HTTPRouter $router)
    {
        // Let extending classes define this
    }

    /**
     * Gets the route cache
     * To use a different route cache than the one returned here, extend this class and override this method
     *
     * @param IContainer $container The dependency injection container
     * @return ICache The route cache
     */
    protected function getRouteCache(IContainer $container)
    {
        return new Cache();
    }

    /**
     * Gets the route compiler
     * To use a different route compiler than the one returned here, extend this class and override this method
     *
     * @param IContainer $container The dependency injection container
     * @return ICompiler The route compiler
     */
    protected function getRouteCompiler(IContainer $container)
    {
        return new Compiler($this->getRouteMatchers($container));
    }

    /**
     * Gets the route dispatcher
     * To use a different route dispatcher than the one returned here, extend this class and override this method
     *
     * @param IContainer $container The dependency injection container
     * @return IDispatcher The route dispatcher
     */
    protected function getRouteDispatcher(IContainer $container)
    {
        return new Dispatcher($container);
    }

    /**
     * Gets the list of route matchers
     *
     * @param IContainer $container The dependency injection container
     * @return IRouteMatcher[] The list of route matchers
     */
    protected function getRouteMatchers(IContainer $container)
    {
        return [
            new PathMatcher(),
            new HostMatcher(),
            new SchemeMatcher()
        ];
    }

    /**
     * Gets the route parser
     * To use a different route parser than the one returned here, extend this class and override this method
     *
     * @param IContainer $container The dependency injection container
     * @return IParser The route parser
     */
    protected function getRouteParser(IContainer $container)
    {
        return new Parser();
    }
}