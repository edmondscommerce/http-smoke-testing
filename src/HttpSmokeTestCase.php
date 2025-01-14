<?php

namespace Shopsys\HttpSmokeTesting;

use Shopsys\HttpSmokeTesting\RouterAdapter\SymfonyRouterAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class HttpSmokeTestCase extends KernelTestCase
{
    protected const APP_ENV   = 'test';
    protected const APP_DEBUG = false;

    /**
     * The main test method for smoke testing of all routes in your application.
     *
     * You must configure the provided RequestDataSets by implementing customizeRouteConfigs method.
     * If you need custom behavior for creating or handling requests in your application you should override the
     * createRequest or handleRequest method.
     *
     * @param \Shopsys\HttpSmokeTesting\RequestDataSet $requestDataSet
     *
     * @dataProvider httpResponseTestDataProvider
     */
    final public function testHttpResponse(RequestDataSet $requestDataSet)
    {
        $requestDataSet->executeCallsDuringTestExecution(static::$kernel->getContainer());

        if ($requestDataSet->isSkipped()) {
            $message = sprintf('Test for route "%s" was skipped.', $requestDataSet->getRouteName());
            $this->markTestSkipped($this->getMessageWithDebugNotes($requestDataSet, $message));
        }

        $request = $this->createRequest($requestDataSet);

        $response = $this->handleRequest($request);

        $this->assertResponse($response, $requestDataSet);
    }

    /**
     * @param \Shopsys\HttpSmokeTesting\RequestDataSet $requestDataSet
     * @param string                                   $message
     *
     * @return string
     */
    protected function getMessageWithDebugNotes(RequestDataSet $requestDataSet, $message)
    {
        if (count($requestDataSet->getDebugNotes()) > 0) {
            $indentedDebugNotes = array_map(function ($debugNote) {
                return "\n" . '  - ' . $debugNote;
            },
                $requestDataSet->getDebugNotes());
            $message            .= "\n" . 'Notes for this data set:' . implode($indentedDebugNotes);
        }

        return $message;
    }

    /**
     * @param \Shopsys\HttpSmokeTesting\RequestDataSet $requestDataSet
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function createRequest(RequestDataSet $requestDataSet)
    {
        $uri = $this->getRouterAdapter()->generateUri($requestDataSet);

        $postRequestBody = $requestDataSet->getPostRequestBody();
        if ('' !== $postRequestBody) {
            return $this->createPostRequest($uri, $requestDataSet);
        }
        $request = Request::create($uri);

        $requestDataSet->getAuth()
                       ->authenticateRequest($request);

        return $request;
    }

    /**
     * @return \Shopsys\HttpSmokeTesting\RouterAdapter\RouterAdapterInterface
     */
    protected function getRouterAdapter()
    {
        $router = static::$kernel->getContainer()->get('router');

        return new SymfonyRouterAdapter($router);
    }

    protected function createPostRequest(string $uri, RequestDataSet $requestDataSet): Request
    {
        $request = Request::create($uri, Request::METHOD_POST, [], [], [], [], $requestDataSet->getPostRequestBody());
        $requestDataSet->getAuth()
                       ->authenticateRequest($request);

        return $request;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleRequest(Request $request)
    {
        return static::$kernel->handle($request);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \Shopsys\HttpSmokeTesting\RequestDataSet   $requestDataSet
     */
    protected function assertResponse(Response $response, RequestDataSet $requestDataSet)
    {
        $failMessage = sprintf(
            'Failed asserting that status code %d for route "%s" is identical to expected %d',
            $response->getStatusCode(),
            $requestDataSet->getRouteName(),
            $requestDataSet->getExpectedStatusCode()
        );
        $this->assertSame(
            $requestDataSet->getExpectedStatusCode(),
            $response->getStatusCode(),
            $this->getMessageWithDebugNotes($requestDataSet, $failMessage)
        );
    }

    /**
     * Data provider for the testHttpResponse method.
     *
     * This method gets all RouteInfo objects provided by RouterAdapter. It then passes them into
     * customizeRouteConfigs() method for customization and returns the resulting RequestDataSet objects.
     *
     * @return \Shopsys\HttpSmokeTesting\RequestDataSet[][]
     */
    final public function httpResponseTestDataProvider()
    {
        $this->setUp();

        $requestDataSetGenerators = [];
        /* @var $requestDataSetGenerators \Shopsys\HttpSmokeTesting\RequestDataSetGenerator[] */

        $allRouteInfo = $this->getRouterAdapter()->getAllRouteInfo();
        foreach ($allRouteInfo as $routeInfo) {
            $requestDataSetGenerators[] = new RequestDataSetGenerator($routeInfo);
        }

        $routeConfigCustomizer = new RouteConfigCustomizer($requestDataSetGenerators);

        $this->customizeRouteConfigs($routeConfigCustomizer);

        /* @var RequestDataSet[] $requestDataSets */
        $requestDataSets = [];
        foreach ($requestDataSetGenerators as $requestDataSetGenerator) {
            $requestDataSets = array_merge($requestDataSets, $requestDataSetGenerator->generateRequestDataSets());
        }

        $return = [];
        foreach ($requestDataSets as $requestDataSet) {
            $return[$requestDataSet->getRouteName()] = [$requestDataSet];
        }

        return $return;
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before data provider is executed and before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel([
                               'environment' => static::APP_ENV,
                               'debug'       => static::APP_DEBUG,
                           ]);
    }

    /**
     * This method must be implemented to customize and configure the test cases for individual routes
     *
     * @param \Shopsys\HttpSmokeTesting\RouteConfigCustomizer $routeConfigCustomizer
     */
    abstract protected function customizeRouteConfigs(RouteConfigCustomizer $routeConfigCustomizer);

    /**
     * A helper method to simply set an expected code for a route name where we expect something like a 302 instead of
     * the default 200
     *
     * @param string $routeName
     * @param int    $expectedCode
     */
    protected function setExpectedCodeForRoute(
        RouteConfigCustomizer $routeConfigCustomizer,
        string $routeName,
        int $expectedCode
    ): void {
        $routeConfigCustomizer->customizeByRouteName(
            $routeName,
            static function (RouteConfig $routeConfig, RouteInfo $routeInfo) use ($expectedCode) {
                $dataSet = $routeConfig->changeDefaultRequestDataSet();
                $dataSet->setExpectedStatusCode($expectedCode);
            }
        );
    }

    /**
     * A helper method to configure a route for POST with post data
     *
     * @param RouteConfigCustomizer $routeConfigCustomizer
     * @param string                $routeName
     * @param string                $postData
     *
     * @throws Exception\RouteNameNotFoundException
     */
    protected function setPostDataForRoute(
        RouteConfigCustomizer $routeConfigCustomizer,
        string $routeName,
        string $postData
    ): void {
        $routeConfigCustomizer->customizeByRouteName(
            $routeName,
            static function (RouteConfig $routeConfig) use ($postData) {
                $routeConfig->changeDefaultRequestDataSet()->setPostRequestBody($postData);
            }
        );
    }
}
