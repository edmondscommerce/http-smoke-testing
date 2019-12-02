<?php

namespace Shopsys\HttpSmokeTesting;

use Shopsys\HttpSmokeTesting\Auth\AuthInterface;
use Shopsys\HttpSmokeTesting\Auth\NoAuth;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestDataSet implements RequestDataSetConfig
{
    private const DEFAULT_EXPECTED_STATUS_CODE = 200;

    /**
     * @var string
     */
    private $routeName;

    /**
     * @var bool
     */
    private $skipped;

    /**
     * @var \Shopsys\HttpSmokeTesting\Auth\AuthInterface|null
     */
    private $auth;

    /**
     * @var int|null
     */
    private $expectedStatusCode;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var string[]
     */
    private $debugNotes;

    /**
     * @var callable[]
     */
    private $callsDuringTestExecution;

    /**
     * @var string
     */
    private $postRequestBody;

    /**
     * @param string $routeName
     */
    public function __construct($routeName)
    {
        $this->routeName                = $routeName;
        $this->skipped                  = false;
        $this->parameters               = [];
        $this->debugNotes               = [];
        $this->callsDuringTestExecution = [];
        $this->postRequestBody          = '';
    }

    /**
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * @return bool
     */
    public function isSkipped()
    {
        return $this->skipped;
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *
     * @return $this
     */
    public function executeCallsDuringTestExecution(ContainerInterface $container)
    {
        foreach ($this->callsDuringTestExecution as $customization) {
            $customization($this, $container);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function skip()
    {
        $this->skipped = true;

        return $this;
    }

    /**
     * Merges values from specified $requestDataSet into this instance.
     *
     * It is used to merge extra RequestDataSet into default RequestDataSet.
     * Values that were not specified in $requestDataSet have no effect on result.
     *
     * @param \Shopsys\HttpSmokeTesting\RequestDataSet $requestDataSet
     *
     * @return $this
     */
    public function mergeExtraValuesFrom(self $requestDataSet)
    {
        if ($requestDataSet->auth !== null) {
            $this->setAuth($requestDataSet->getAuth());
        }
        if ($requestDataSet->expectedStatusCode !== null) {
            $this->setExpectedStatusCode($requestDataSet->getExpectedStatusCode());
        }
        foreach ($requestDataSet->getParameters() as $name => $value) {
            $this->setParameter($name, $value);
        }
        foreach ($requestDataSet->getDebugNotes() as $debugNote) {
            $this->addDebugNote($debugNote);
        }
        foreach ($requestDataSet->callsDuringTestExecution as $callback) {
            $this->addCallDuringTestExecution($callback);
        }
        if ($requestDataSet->getPostRequestBody()) {
            $this->setPostRequestBody($requestDataSet->getPostRequestBody());
        }

        return $this;
    }

    /**
     * @return \Shopsys\HttpSmokeTesting\Auth\AuthInterface
     */
    public function getAuth()
    {
        if ($this->auth === null) {
            return new NoAuth();
        }

        return $this->auth;
    }

    /**
     * @param \Shopsys\HttpSmokeTesting\Auth\AuthInterface $auth
     *
     * @return $this
     */
    public function setAuth(AuthInterface $auth)
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * @return int
     */
    public function getExpectedStatusCode()
    {
        if ($this->expectedStatusCode === null) {
            return self::DEFAULT_EXPECTED_STATUS_CODE;
        }

        return $this->expectedStatusCode;
    }

    /**
     * @param int $code
     *
     * @return $this
     */
    public function setExpectedStatusCode($code)
    {
        $this->expectedStatusCode = $code;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDebugNotes()
    {
        return $this->debugNotes;
    }

    /**
     * @param string $debugNote
     *
     * @return $this
     */
    public function addDebugNote($debugNote)
    {
        $this->debugNotes[] = $debugNote;

        return $this;
    }

    /**
     * Provided $callback will be called with instance of this and ContainerInterface as arguments
     *
     * Useful for code that needs to access the same instance of container as the test method.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function addCallDuringTestExecution($callback)
    {
        $this->callsDuringTestExecution[] = $callback;

        return $this;
    }

    /**
     * @return string
     */
    public function getPostRequestBody(): string
    {
        return $this->postRequestBody;
    }

    /**
     * @param string $postRequestBody
     */
    public function setPostRequestBody(string $postRequestBody): void
    {
        $this->postRequestBody = $postRequestBody;
    }
}
