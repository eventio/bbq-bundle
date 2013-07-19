<?php
namespace Eventio\BBQBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

/**
 * @author Ville Mattila <ville@eventio.fi>
 */
class EventioBBQExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('pheanstalk.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if ($config['pheanstalk_connections']) {
            $this->loadPheanstalk($container, $config['pheanstalk_connections']);
        }
        if ($config['queues']) {
            $this->loadQueues($container, $config['queues']);
        }
    }

    public function getAlias()
    {
        return 'eventio_bbq';
    }

    private function loadPheanstalk(ContainerBuilder $container, $config)
    {
        foreach ($config as $connectionId => $connectionConfig) {
            $definition = new \Symfony\Component\DependencyInjection\Definition();
            $definition->setClass($container->getParameter('eventio_bbq.defaults.pheanstalk.class'));
            $definition->setArguments(array($connectionConfig['host'], $connectionConfig['port']));

            $container->setDefinition(sprintf('eventio_bbq.pheanstalk.%s', $connectionId), $definition);
        }
    }

    private function loadQueues(ContainerBuilder $container, $config)
    {
        foreach ($config as $queueId => $queueConfig) {
            $type = $queueConfig['type'];

            if ($type == 'pheanstalk') {
                $pheanstalkId = array_key_exists('pheanstalk_id', $queueConfig) ? $queueConfig['pheanstalk_id'] : 'default';
                if (!array_key_exists('tube', $queueConfig)) {
                    throw new \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException('Queue configuration "' . $queueId . '" does not have tube.');
                }

                $definition = new \Symfony\Component\DependencyInjection\Definition();
                $definition->setClass($container->getParameter('eventio_bbq.defaults.queue.pheanstalk.class'));
                $definition->setArguments(array($queueId, $container->getDefinition(sprintf('eventio_bbq.pheanstalk.%s', $pheanstalkId)), $queueConfig['tube']));
            } elseif ($type == 'directory') {
                $definition = new \Symfony\Component\DependencyInjection\Definition();
                $definition->setClass($container->getParameter('eventio_bbq.defaults.queue.directory.class'));
                $definition->setArguments(array($queueId, $queueConfig['directory']));
            } else {
                throw new \Exception('Invalid queue type ' . $type);
            }

            $container->setDefinition(sprintf('eventio_bbq.%s', $queueId), $definition);

            $container->getDefinition('eventio_bbq')
                ->addMethodCall('registerQueue', array($container->getDefinition(sprintf('eventio_bbq.%s', $queueId))));
        }
    }

}
