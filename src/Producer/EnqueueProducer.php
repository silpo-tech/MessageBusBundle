<?php

declare(strict_types=1);

namespace MessageBusBundle\Producer;

use MessageBusBundle\AmqpTools\RabbitMqDlxDelayStrategy;
use Enqueue\Client\Config;
use Enqueue\ConnectionFactoryFactoryInterface;
use Interop\Amqp\AmqpQueue;
use Interop\Queue\Context;
use Interop\Queue\Destination;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use MessageBusBundle\Events;
use MessageBusBundle\Events\PrePublishEvent;
use MessageBusBundle\MessageBus;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\UuidV6;

class EnqueueProducer implements ProducerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const TOPIC_NAME = 'enqueue.topic';

    private const MAX_RETRIES = 3;

    private const WAIT_BEFORE_RETRY = 50000;

    private Context $context;

    private Config $config;

    private ConnectionFactoryFactoryInterface $factory;

    private EventDispatcherInterface $eventDispatcher;

    private string $topicName;

    /**
     * @param Config                            $config
     * @param ConnectionFactoryFactoryInterface $factory
     * @param EventDispatcherInterface          $eventDispatcher
     */
    public function __construct(
        Config $config,
        ConnectionFactoryFactoryInterface $factory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->config = $config;
        $this->factory = $factory;
        $this->eventDispatcher = $eventDispatcher;

        $this->topicName = $this->createTopicName($config);
        $this->context = $this->factory->create($this->config->getTransportOptions())->createContext();
    }

    /**
     * @param string      $topic
     * @param string      $message
     * @param array       $headers
     * @param int         $delay
     * @param string|null $exchange
     *
     * @return $this
     */
    public function send(string $topic, string $message, array $headers = [], int $delay = 0, string $exchange = MessageBus::DEFAULT_EXCHANGE): self
    {
        return $this->sendMessage($topic, $this->createMessage($message, $headers), $delay, $exchange);
    }

    /**
     * @param string      $topic
     * @param Message     $message
     * @param int         $delay
     * @param string|null $exchange
     *
     * @return $this
     */
    public function sendMessage(string $topic, Message $message, int $delay = 0, string $exchange = MessageBus::DEFAULT_EXCHANGE): self
    {
        $message->setRoutingKey($topic);
        $message->setProperty(self::TOPIC_NAME, $topic);

        //create topic
        $destination = $this->context->createTopic($exchange ?? $this->topicName);

        return $this->doSend($destination, $message, $delay);
    }

    /**
     * @param string $queue
     * @param string $message
     * @param array  $headers
     * @param int    $delay
     *
     * @return $this
     */
    public function sendToQueue(string $queue, string $message, array $headers = [], int $delay = 0): self
    {
        return $this->sendMessageToQueue($queue, $this->createMessage($message, $headers), $delay);
    }

    /**
     * @param string $queue
     * @param string $message
     * @param array  $headers
     * @param int    $delay
     *
     * @return $this
     */
    public function sendMessageToQueue(string $queue, Message $message, int $delay = 0): self
    {
        //create destination eueue
        $destination = $this->context->createQueue($queue);
        if ($destination instanceof AmqpQueue) {
            $destination->addFlag(AmqpQueue::FLAG_DURABLE);
        }

        return $this->doSend($destination, $message, $delay);
    }

    /**
     * @param Destination $destination
     * @param Message     $message
     * @param int         $delay
     *
     * @return $this
     */
    public function doSend(Destination $destination, Message $message, int $delay = 0): self
    {
        $this->eventDispatcher->dispatch(new PrePublishEvent($message), Events::PRODUCER__PRE_PUBLISH);

        $attempt = 0;
        do {
            try {
                if ($destination instanceof AmqpQueue) {
                    $this->context->declareQueue($destination);
                }
                $this->createProducer($delay)->send($destination, $message);

                return $this;
            } catch (\Exception $ex) {
                $attempt++;

                $this->logger->debug('EnqueueProducer send attempt', ['errorMessage' => $ex->getMessage(), 'attempt' => $attempt, 'correlationId' => (string) $message->getCorrelationId()]);

                $this->reconnect();

                if ($attempt > self::MAX_RETRIES) {
                    $this->logger->error('EnqueueProducer send error', ['errorMessage' => $ex->getMessage(), 'attempt' => $attempt, 'correlationId' => (string) $message->getCorrelationId()]);

                    throw $ex;
                }

                if ($attempt > 1) {
                    usleep(pow(2, $attempt) * self::WAIT_BEFORE_RETRY);
                }
            }
        } while (true);
    }

    /**
     * @param string $message
     * @param array  $headers
     *
     * @return Message
     */
    private function createMessage(string $message, array $headers = []): Message
    {
        $message = $this->context->createMessage($message);
        $message->setTimestamp();
        foreach ($headers as $key => $value) {
            $message->setProperty($key, $value);
        }

        if(!$message->getCorrelationId()) {
            $message->setCorrelationId(UuidV6::generate());
        }

        return $message;
    }

    /**
     * @param int $delay
     *
     * @return Producer
     */
    private function createProducer(int $delay = 0): Producer
    {
        $producer = $this->context->createProducer();
        if ($delay > 0) {
            $producer
                ->setDelayStrategy(new RabbitMqDlxDelayStrategy())
                ->setDeliveryDelay($delay);
        }

        return $producer;
    }

    /**
     * @param Config $config
     *
     * @return string
     */
    private function createTopicName(Config $config): string
    {
        return strtolower(implode($config->getSeparator(), array_filter([$config->getPrefix(), $config->getRouterTopic()])));
    }

    /**
     * @return $this
     */
    private function reconnect(): self
    {
        $this->context = $this->factory->create($this->config->getTransportOptions())->createContext();

        return $this;
    }
}
