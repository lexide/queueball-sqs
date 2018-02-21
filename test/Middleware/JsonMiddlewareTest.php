<?php

namespace Lexide\QueueBall\Sqs\Test\Middleware;

use Lexide\QueueBall\Sqs\Middleware\JsonMiddleware;

class JsonMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JsonMiddleware
     */
    protected $jsonMiddleware;

    public function setUp()
    {
        $this->jsonMiddleware = new JsonMiddleware();
    }

    public function testRequest()
    {
        $this->assertEquals(
            '{"This is an array":"of stuff"}',
            $this->jsonMiddleware->request(["This is an array" => "of stuff"])
        );
    }

    public function testResponse()
    {
        $this->assertEquals(
            ["This is an array" => "of stuff"],
            $this->jsonMiddleware->response('{"This is an array":"of stuff"}')
        );
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidResponse()
    {
        $this->jsonMiddleware->response('{"This is an invalid JSON string"}');
    }
}
