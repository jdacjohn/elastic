<?php

namespace PortlandLabs\Elastic\Job;

use Concrete\Core\Job\JobResult;
use Concrete\Core\Job\QueueableJob;
use ZendQueue\Queue;

abstract class ForkedProcessJob extends QueueableJob
{

    /** @var array|array[] List of processes and the messages they are processing */
    protected $processes = [];

    /** @var int Total number of workers */
    protected $workers = 5;

    /** @var int Number of messages to send to a worker */
    protected $workerBatch = 50;

    /** @var string|null string if we're a worker, null if we're the main process */
    protected $workerID;

    /**
     * Execute the job
     * This method forks the current process into multiple processes
     * Each worker processes `$this->workerBatch` items then dies.
     * Try not to access the database in worker objects if you don't need to.
     * @return JobResult
     */
    public function executeJob()
    {
        /** @var Queue $queue */
        $queue = $this->markStarted();
        $this->start($queue);

        if ($queue->count()) {
            $this->launchWorkers($this->workers, $queue);
            $this->watchWorkers($queue);
        }

        $result = $this->finish($queue);
        return $this->markCompleted(0, $result);
    }

    /**
     * Fork the current process to create a child worker process
     * @param $workerCallable
     * @param array $messages
     */
    private function fork($workerCallable, $messages = [])
    {
        if (!$messages) {
            return;
        }

        // Close db connection for forking
        \Database::connection()->close();

        $worker = md5(mt_rand());

        // If we get an ID back, we're on the main process
        if ($id = \pcntl_fork()) {
            if ($id == -1) {
                throw new \RuntimeException('Failed to fork process.');
            }

            $this->processes[$id] = ['id' => $worker, 'messages' => $messages];

            $this->report("Lauched process {$id} with ID {$worker}");

            // We're in the main process, just return.
            return;
        }

        // If we got 0, we're in the fork.
        $this->workerID = $worker;

        // Sleep from 200ms to 1s to give the main process a chance to start all processes
        usleep(mt_rand(200, 1000) * 1000);

        // Do whatever work is needed, then exit;
        $workerCallable($messages, function ($message) {
        });
        exit;
    }

    /**
     * Launch all workers at the beginning
     * @param $workers
     * @param $queue
     */
    private function launchWorkers($workers, $queue)
    {
        while ($workers--) {
            $this->launchWorker($this->getMessages($queue));

            // Sleep somewhere between 0 and 1 second
            usleep(mt_rand(0, 1000));
        }
    }

    /**
     * Pay attention to workers and unset them when they die.
     * @param \ZendQueue\Queue $queue
     */
    private function watchWorkers(Queue $queue)
    {
        while (count($this->processes)) {
            foreach ($this->processes as $pid => $value) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);

                // If the process has already exited
                if ($res == -1 || $res > 0) {
                    $worker = $this->processes[$pid];
                    $this->report("{$worker['id']} has finished.");

                    // Delete worker messages
                    foreach ($worker['messages'] as $message) {
                        $queue->deleteMessage($message);
                    }

                    unset($this->processes[$pid]);

                    $messages = $this->getMessages($queue);
                    if (count($messages)) {
                        // There's still more, lets launch another one
                        $this->launchWorker($messages);
                    }
                }
            }
        }
    }

    /**
     * Launch a worker to work on a list of messages
     * @param array $messages
     */
    private function launchWorker($messages = [])
    {
        $this->fork(function ($messages, $processed) {
            $count = count($messages);
            $this->report("Processing {$count} messages");
            foreach ($messages as $message) {
                $this->processQueueItem($message);
                $processed($message);
            }
        }, $messages);
    }

    /**
     * Get a list of messages to do work on
     * @param $queue
     * @return mixed
     */
    private function getMessages($queue)
    {
        return $queue->receive($this->workerBatch);
    }

    protected function report($message)
    {
        $id = $this->workerID ?: 'Main Process';
        echo "{$id}: {$message}\n";
    }

}
