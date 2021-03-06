Symfony 2 Bundle for Eventio BBQ Library
========================================

**Work in progress** for Symfony2 Bundle for [Eventio BBQ](http://www.github.com/eventio/bbq),
Message Queue Abstraction Library for PHP.

Installation
------------

Via [composer.json](http://getcomposer.org/doc/01-basic-usage.md#composer-json-project-setup)

    "require": {
        "eventio/bbq-bundle": "dev-master"
    }

Configuration
-------------

Register the bundle in your AppKernel.php:

    public function registerBundles()
    {
        $bundles = array(
            // ...
            new \Eventio\BBQBundle\EventioBBQBundle(),
            // ...
        );
    }

Configure each queue in your `config.yml` as follows:

    eventio_bbq:
        queues:
            queue_id:
                type: directory
                directory: /tmp/directory

You can insert as many queues as you want below `queues`. See Queue Types below for detailed configuration reference.

Usage
-----

After the bundle and queues are configured, access the BBQ instance and push/fetch jobs from the queues:

    $bbq = $this->get('eventio_bbq'); // Instance of \Eventio\BBQ()
    $bbq->pushJob('queue_id', 'Payload');

As each queue is registered as individual Symfony2 service, you can access them directly:

    $queue = $this->get('eventio_bbq.queue_id'); // Instance of \Eventio\BBQ\Queue\DirectoryQueue();
    $queue->pushJob(new StringPayload('Some payload'));

Queue Types
-----------

### DirectoryQueue

`type: directory`

**Parameters**

 - `directory` The directory acting as the queue storage
 
### RedisTubeQueue

`type: predis`

**Parameters**

 - `key` The Redis key for the queue
 - `predis_id` The Predis connection id (default = `default`)
 - `config` Further queue configuration (default = `array()`)

**Multiple Redis servers**

Predis configuration can be customized under section `predis_connections`.

Default configuration (when nothing is defined) is as follows.

    eventio_bbq:
        predis_connections:
            default:
                params: tcp://127.0.0.1:6379
                options: ~

Two different queues under different hosts could be configured like:

    eventio_bbq:
        predis_clients:
            host1:
                params: tcp://192.168.1.1:6379
            host2:
                params: tcp://192.168.1.2:6379
        queues:
            queue1:
                predis_id: host1
                key: queue1_at_host1
            queue2:
                predis_id: host2
                key: queue2_at_host2

Usage of the two queues inside the application:

    $bbq->pushJob('queue1', 'Job to redis server 1, queue "queue1_at_host1"');
    $bbq->pushJob('queue2', 'Job to redis server 2, queue "queue2_at_host2"');

**Master/Slave replication**

`params` and `options` under `predis_clients` section are passed to `\Predis\Client`
constructor as is. Therefore you can configure them freely, including master/slave setup:

    eventio_bbq:
        predis_clients:
            master_slave:
                params: ["tcp://192.168.1.1:6379?alias=master", "tcp://192.168.1.2:6379?alias=slave"]
                options: [replication: true]
        queues:
            replicated_queue:
                predis_id: master_slave
                key: queue

See all possible [Connection parameters](https://github.com/nrk/predis/wiki/Connection-Parameters) (=`params`)
and [Client Options](https://github.com/nrk/predis/wiki/Client-Options) (=`options`)

### PheanstalkTubeQueue

`type: pheanstalk`

**Parameters**

 - `tube` The pheanstalk tube acting as the queue
 - `pheanstalk_id` The pheanstalk connection id (default = `default`)

**Multiple beanstalkd servers**

Pheanstalk configuration can be customized under section `pheanstalk_connections`.

Default configuration (when nothing is defined) is as follows.

    eventio_bbq:
        pheanstalk_connections:
            default:
                host: 127.0.0.1

Two different queues under different hosts could be configured like:

    eventio_bbq:
        pheanstalk_connections:
            host1:
                host: 192.168.0.1
            host2:
                host: 192.168.0.2
        queues:
            queue1:
                pheanstalk_id: host1
                tube: tube_at_host1
            queue2:
                pheanstalk_id: host2
                tube: tube_at_host2

Usage of the two queues inside the application is simple:

    $bbq->pushJob('queue1', 'Job to pheanstalk server 1, tube tube_at_host1');
    $bbq->pushJob('queue2', 'Job to pheanstalk server 2, tube tube_at_host2');

Contribute
----------

As the library is in its very early stages, you are more than welcome to contribute the work
 - by fixing bugs
 - by writing new tests
 - by implementing new queue types
 - by giving ideas and comments on the code

License
-------

Copyright [Eventio Oy](https://github.com/eventio), [Ville Mattila](https://github.com/vmattila), 2013

Released under the [The MIT License](http://www.opensource.org/licenses/mit-license.php)