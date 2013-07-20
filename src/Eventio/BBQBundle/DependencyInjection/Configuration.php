<?php
namespace Eventio\BBQBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Ville Mattila <ville@eventio.fi>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('eventio_bbq');

        $rootNode
            ->children()
                ->variableNode('queues')->end()
                ->arrayNode('pheanstalk_connections')
                    ->useAttributeAsKey('id')
                    ->defaultValue(array(
                        'default' => array(
                            'host' => '127.0.0.1',
                            'port' => '11300',
                        )
                    ))
                    ->prototype('array')
                        ->children()
                            ->scalarNode('host')->end()
                            ->scalarNode('port')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('predis_clients')
                    ->useAttributeAsKey('id')
                    ->defaultValue(array(
                        'default' => array(
                            'params' => 'tcp://127.0.0.1:6379',
                            'options' => array(),
                        )
                    ))
                    ->prototype('array')
                        ->children()
                            ->variableNode('params')->end()
                            ->variableNode('options')
                                ->defaultValue(null)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}