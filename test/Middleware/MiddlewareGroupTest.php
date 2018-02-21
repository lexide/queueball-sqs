<?php

namespace Lexide\QueueBall\Sqs\Test\Middleware;

use Lexide\QueueBall\Sqs\Middleware\MiddlewareGroup;
use Lexide\QueueBall\Sqs\Middleware\MiddlewareInterface;
use Mockery\Mock;

class MiddlewareGroupTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MiddlewareGroup
     */
    protected $middleware;

    public function setUp()
    {
        // First will add the word "foo" to the start of a string, and remove it if it's there
        /** @var MiddlewareInterface|Mock $first */
        $first = \Mockery::mock(MiddlewareInterface::class);
        $first->shouldReceive("request")->andReturnUsing(function ($body) {
            return "foo " . $body;
        });
        $first->shouldReceive("response")->andReturnUsing(function ($body) {
            if (strpos($body, "foo ")===0) {
                return substr($body, 4);
            }

            return $body;
        });

        // Second will add the word "bar" to the start of a string, and remove it if it's there
        /** @var MiddlewareInterface|Mock $second */
        $second = \Mockery::mock(MiddlewareInterface::class);
        $second->shouldReceive("request")->andReturnUsing(function ($body) {
            return "bar " . $body;
        });
        $second->shouldReceive("response")->andReturnUsing(function ($body) {
            if (strpos($body, "bar ")===0) {
                return substr($body, 4);
            }

            return $body;
        });

        $this->middleware = new MiddlewareGroup([$first, $second]);
    }

    public function testRequest()
    {
        $this->assertEquals(
            "bar foo sandwich",
            $this->middleware->request("sandwich")
        );
    }

    public function testResponse()
    {
        $this->assertEquals(
            "sandwich",
            $this->middleware->response("bar foo sandwich")
        );
    }
}
