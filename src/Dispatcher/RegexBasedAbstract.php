<?php
declare(strict_types=1);

namespace FastRoute\Dispatcher;

use FastRoute\Dispatcher;

abstract class RegexBasedAbstract implements Dispatcher
{
    /** @var mixed[][] */
    protected $staticRouteMap = [];

    /** @var mixed[] */
    protected $variableRouteData = [];

    /**
     * @param mixed[] $data
     */
    public function __construct(array $data)
    {
        [$this->staticRouteMap, $this->variableRouteData] = $data;
    }

    /**
     * @param mixed[] $routeData
     *
     * @return mixed[]
     */
    abstract protected function dispatchVariableRoute(array $routeData, string $uri): array;

    /**
     * {@inheritDoc}
     */
    public function dispatch(string $httpMethod, string $uri): array
    {
        $result = $this->resolveByMethod($httpMethod, $uri);
        if ($result[0] === self::FOUND) {
            $result[3] = $this->findAllowedMethods($httpMethod, $uri);

            return $result;
        }

        // For HEAD requests, attempt fallback to GET
        if ($httpMethod === 'HEAD') {
            $result = $this->resolveByMethod('GET', $uri);
            if ($result[0] === self::FOUND) {
                $result[3] = $this->findAllowedMethods($httpMethod, $uri);

                return $result;
            }
        }

        // If nothing else matches, try fallback routes
        $result = $this->resolveByMethod('*', $uri);
        if ($result[0] === self::FOUND) {
            $result[3] = self::ALLOWED_METHODS_ANY;

            return $result;
        }

        $allowedMethods = $this->findAllowedMethods($httpMethod, $uri);

        // If there are no allowed methods the route simply does not exist
        if ($allowedMethods !== self::ALLOWED_METHODS_NONE) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods];
        }

        return [self::NOT_FOUND];
    }

    private function resolveByMethod(string $httpMethod, string $uri): array
    {
        if (isset($this->staticRouteMap[$httpMethod][$uri])) {
            $handler = $this->staticRouteMap[$httpMethod][$uri];

            return [self::FOUND, $handler, []];
        }

        if (isset($this->variableRouteData[$httpMethod])) {
            return $this->dispatchVariableRoute($this->variableRouteData[$httpMethod], $uri);
        }

        return [self::NOT_FOUND];
    }

    private function findAllowedMethods(string $httpMethod, string $uri): array
    {
        // Find allowed methods for this URI by matching against all other HTTP methods as well
        $allowedMethods = [];

        foreach ($this->staticRouteMap as $method => $uriMap) {
            if (isset($uriMap[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($this->variableRouteData as $method => $routeData) {
            if ($method === $httpMethod) {
                continue;
            }

            $result = $this->dispatchVariableRoute($routeData, $uri);
            if ($result[0] === self::FOUND) {
                $allowedMethods[] = $method;
            }
        }

        return $allowedMethods;
    }
}
