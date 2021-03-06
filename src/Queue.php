<?php

namespace Lexide\QueueBall\Sqs;

use Aws\Sqs\SqsClient;
use Lexide\QueueBall\Message\QueueMessage;
use Lexide\QueueBall\Queue\AbstractQueue;
use Lexide\QueueBall\Message\QueueMessageFactoryInterface;
use Lexide\QueueBall\Sqs\Middleware\MiddlewareInterface;

/**
 *
 */
class Queue extends AbstractQueue
{

    const DEFAULT_MESSAGE_LOCK_TIMEOUT = 120;

    /**
     * @var string
     */
    protected $queueUrl;

    /**
     * @var SqsClient
     */
    protected $queueClient;

    /**
     * @var QueueMessageFactoryInterface
     */
    protected $messageFactory;

    /**
     * @var MiddlewareInterface
     */
    protected $middleware;

    /**
     * @var int
     */
    protected $waitTime = 20;

    /**
     * @param SqsClient $sqsClient
     * @param QueueMessageFactoryInterface $messageFactory
     * @param MiddlewareInterface $middleware
     * @param string|null $queueId
     */
    public function __construct(
        SqsClient $sqsClient,
        QueueMessageFactoryInterface $messageFactory,
        MiddlewareInterface $middleware,
        $queueId = null
    )
    {
        parent::__construct($queueId);
        $this->queueClient = $sqsClient;
        $this->middleware = $middleware;
        $this->messageFactory = $messageFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function setQueueId($queueId)
    {
        parent::setQueueId($queueId);
        $this->queueUrl = null;
    }

    /**
     * @param string $queueId
     * @return string
     */
    protected function getQueueUrl($queueId)
    {
        if (empty($this->queueUrl)) {
            if (empty($queueId)) {
                $queueId = $this->getQueueId();
            }
            $response = $this->queueClient->getQueueUrl(["QueueName" => $queueId]);
            $this->queueUrl = $response->get("QueueUrl");
        }
        return $this->queueUrl;
    }

    /**
     * {@inheritDoc}
     */
    public function createQueue($queueId, $options = [])
    {
        $timeout = (empty($options["messageLockTimeout"]) || !is_numeric($options["messageLockTimeout"]))
            ? self::DEFAULT_MESSAGE_LOCK_TIMEOUT
            : (int) $options["messageLockTimeout"];
        $attributes = [
            "VisibilityTimeout" => $timeout
        ];
        $this->queueClient->createQueue([
            "QueueName" => $queueId,
            "Attributes" => $attributes
        ]);
        $this->setQueueId($queueId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteQueue($queueId = null)
    {
        $queueUrl = $this->getQueueUrl($queueId);
        $this->queueClient->deleteQueue(["QueueUrl" => $queueUrl]);
    }

    /**
     * {@inheritDoc}
     */
    public function sendMessage($messageBody, $queueId = null)
    {
        $queueUrl = $this->getQueueUrl($queueId);

        $this->queueClient->sendMessage([
            "QueueUrl" => $queueUrl,
            "MessageBody" => $this->middleware->request($messageBody)
        ]);
    }

    /**
     * This can be used to send up to 10 entries and up to a total of 264kb
     *
     * {@inheritDoc}
     */
    public function sendMessageBatch($messageBodies=[], $queueId=null)
    {
        if (!is_array($messageBodies)) {
            throw new \Exception("MessageBodies must be an array");
        }

        $queueUrl = $this->getQueueUrl($queueId);
        $entries = [];

        $i = 0;
        foreach ($messageBodies as $messageBody) {
            $entries[] = [
                "Id" => $i,
                "MessageBody" => $this->middleware->request($messageBody)
            ];

            $i++;
        }

        $this->queueClient->sendMessageBatch([
            "QueueUrl" => $queueUrl,
            "Entries" => $entries
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function receiveMessage($queueId = null, $waitTime = null)
    {
        if (empty($queueId)) {
            // have to do this here as we need the ID later in this method
            $queueId = $this->getQueueId();
        }
        $queueUrl = $this->getQueueUrl($queueId);
        $message = $this->queueClient->receiveMessage([
            "QueueUrl" => $queueUrl,
            "WaitTimeSeconds" => (isset($waitTime) ? $waitTime : $this->waitTime)
        ]);
        return $this->messageFactory->createMessage($message->toArray(), $queueId);

    }

    /**
     * {@inheritDoc}
     */
    public function completeMessage(QueueMessage $message)
    {
        $queueUrl = $this->getQueueUrl($message->getQueueId());
        $this->queueClient->deleteMessage([
            "QueueUrl" => $queueUrl,
            "ReceiptHandle" => $message->getReceiptId()
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function returnMessage(QueueMessage $message)
    {
        $queueUrl = $this->getQueueUrl($message->getQueueId());
        $this->queueClient->changeMessageVisibility([
            "QueueUrl" => $queueUrl,
            "ReceiptHandle" => $message->getReceiptId(),
            "VisibilityTimeout" => 0
        ]);
    }

} 
