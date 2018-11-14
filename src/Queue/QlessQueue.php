<?php

namespace LaravelQless\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use LaravelQless\Job\QlessJob;
use Qless\Client;

/**
 * Class QlessQueue
 * @package LaravelQless\Queue
 */
class QlessQueue extends Queue implements QueueContract
{
    /**
     * @var Client
     */
    private $connect;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    protected $connectionName;

    /**
     * @var string
     */
    protected $defaultQueue;

    /**
     * QlessQueue constructor.
     * @param Client $connect
     * @param array $config
     */
    public function __construct(Client $connect, array $config)
    {
        $this->connect = $connect;
        $this->defaultQueue = $config['queue'] ?? '';
        $this->config = $config;
    }

    /**
     * @return Client
     */
    private function getConnection(): Client
    {
        return $this->connect;
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function size($queue = null)
    {
        return $this->getConnection()->length($queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queueName
     * @param  array   $options
     * @return mixed
     */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        $payloadData = array_merge(json_decode($payload, true), $options);

        $queueName = $queueName ?? $this->defaultQueue;

        $queue = $this->getConnection()->queues[$queueName];

        return $queue->put(
            $payloadData['job'],
            $payloadData['data'],
            null,
            $payloadData['timeout'],
            $payloadData['maxTries']
        );
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed   $data
     * @param  string  $queueName
     * @return mixed
     */
    public function push($job, $data = '', $queueName = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queueName, $data);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed   $data
     * @param  string  $queueName
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queueName = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queueName, ['timeout' => $delay]);
    }

    /**
     * Recurring Jobs
     *
     * @param int $interval
     * @param string $job
     * @param array $data
     * @param string $queueName
     * @return string
     */
    public function recur(int $interval, string $job, array $data, ?string $queueName = null)
    {
        /** @var \Qless\Queues\Queue $queue */
        $queue = $this->getConnection()->queues[$queueName];

        return $queue->recur($job, $data, $interval);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queueName
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queueName = null)
    {
        $queueName = $queueName ?? $this->defaultQueue;

        /** @var \Qless\Queues\Queue $queue */
        $queue = $this->getConnection()->queues[$queueName];

        $job = $queue->pop();

        if (!$job) {
            return null;
        }

        $payload = $this->createPayload($job->getKlass(), $job->getData());

        \Log::error('test_job perform', ['test' => self::class]);

        return new QlessJob(
            $job,
            $payload,
            $this->getConnectionName()
        );
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Set the connection name for the queue.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnectionName($name): self
    {
        $this->connectionName = $name;

        return $this;
    }
}