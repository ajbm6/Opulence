<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Dispatches routes to the appropriate controllers
 */
namespace RDev\Models\Routing;
use RDev\Controllers;
use RDev\Models\HTTP;
use RDev\Models\IoC;

class Dispatcher
{
    /** @var IoC\IContainer The dependency injection container */
    private $iocContainer = null;

    /**
     * @param IoC\IContainer $iocContainer The dependency injection container
     */
    public function __construct(IoC\IContainer $iocContainer)
    {
        $this->iocContainer = $iocContainer;
    }

    /**
     * Dispatches the input route
     *
     * @param Route $route The route to dispatch
     * @param HTTP\Request $request The request made by the user
     * @param array @routeVariables The array of route variable names to their values
     * @return HTTP\Response The response from the controller or pre/post filters if there was one
     * @throws RouteException Thrown if the method could not be called on the controller
     */
    public function dispatch(Route $route, HTTP\Request $request, array $routeVariables)
    {
        $controller = $this->createController($route->getControllerName());

        // Do our pre-filters
        if(($preFilterReturnValue = $this->doPreFilters($route, $request)) !== null)
        {
            return $preFilterReturnValue;
        }

        // Call our controller
        $controllerResponse = $this->callController($controller, $route, $routeVariables);

        // Do our post-filters
        if(($postFilterReturnValue = $this->doPostFilters($route, $request, $controllerResponse)) !== null)
        {
            return $postFilterReturnValue;
        }

        // Nothing returned a value, so return a basic HTTP response
        return new HTTP\Response();
    }

    /**
     * Calls the method on the input controller
     *
     * @param Controllers\Controller $controller The instance of the controller to call
     * @param Route $route The route being dispatched
     * @param array $routeVariables The list of route variable names to their values
     * @return HTTP\Response Returns the value from the controller method
     * @throws RouteException Thrown if the method could not be called on the controller
     */
    private function callController(Controllers\Controller $controller, Route $route, array $routeVariables)
    {
        $parameters = [];

        try
        {
            $reflection = new \ReflectionMethod($controller, $route->getControllerMethod());

            if($reflection->isPrivate())
            {
                throw new RouteException("Method {$route->getControllerMethod()} is private");
            }

            // Match the route variables to the method parameters
            foreach($reflection->getParameters() as $parameter)
            {
                if(isset($routeVariables[$parameter->getName()]))
                {
                    // There is a value set in the route
                    $parameters[$parameter->getPosition()] = $routeVariables[$parameter->getName()];
                }
                elseif(($defaultValue = $route->getDefaultValue($parameter->getName())) !== null)
                {
                    // There was a default value set in the route
                    $parameters[$parameter->getPosition()] = $defaultValue;
                }
                elseif(!$parameter->isDefaultValueAvailable())
                {
                    // There is no value/default value for this variable
                    throw new RouteException(
                        "No value set for parameter {$parameter->getName()}"
                    );
                }
            }

            return call_user_func_array([$controller, "callMethod"], [$route->getControllerMethod(), $parameters]);
        }
        catch(\ReflectionException $ex)
        {
            throw new RouteException(
                sprintf(
                    "Reflection failed for method %s in controller %s: %s",
                    $route->getControllerMethod(),
                    get_class($controller),
                    $ex
                )
            );
        }
    }

    /**
     * Creates an instance of the input controller
     *
     * @param string $controllerName The fully-qualified name of the controller class to instantiate
     * @return Controllers\Controller The instantiated controller
     * @throws RouteException Thrown if the controller could not be instantiated
     */
    private function createController($controllerName)
    {
        if(!class_exists($controllerName))
        {
            throw new RouteException("Controller class $controllerName does not exist");
        }

        $controller = $this->iocContainer->makeShared($controllerName);

        if(!$controller instanceof Controllers\Controller)
        {
            throw new RouteException("Controller class $controllerName does not extend the base controller");
        }

        return $controller;
    }

    /**
     * Executes a route's post-filters
     *
     * @param Route $route The route that is being dispatched
     * @param HTTP\Request $request The request made by the user
     * @param HTTP\Response $response The response returned by the controller
     * @return HTTP\Response|null The response if any filter returned one, otherwise null
     * @throws RouteException Thrown if the filter is not of the correct type
     */
    private function doPostFilters(Route $route, HTTP\Request $request, HTTP\Response $response = null)
    {
        foreach($route->getPostFilters() as $filterClassName)
        {
            $filter = $this->iocContainer->makeShared($filterClassName);

            if(!$filter instanceof Filters\IFilter)
            {
                throw new RouteException("Filter $filterClassName does not implement IFilter");
            }

            // Don't send this response to the next filter if it didn't return anything
            if(($thisResponse = $filter->run($route, $request, $response)) !== null)
            {
                $response = $thisResponse;
            }
        }

        return $response;
    }

    /**
     * Executes a route's pre-filters
     *
     * @param Route $route The route that is being dispatched
     * @param HTTP\Request $request The request made by the user
     * @return HTTP\Response|null The response if any filter returned one, otherwise null
     * @throws RouteException Thrown if the filter is not of the correct type
     */
    private function doPreFilters(Route $route, HTTP\Request $request)
    {
        foreach($route->getPreFilters() as $filterClassName)
        {
            $filter = $this->iocContainer->makeShared($filterClassName);

            if(!$filter instanceof Filters\IFilter)
            {
                throw new RouteException("Filter $filterClassName does not implement IFilter");
            }

            // If the filter returned anything, return it right away
            if(($response = $filter->run($route, $request)) !== null)
            {
                return $response;
            }
        }

        return null;
    }
} 