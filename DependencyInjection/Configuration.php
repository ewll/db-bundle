<?php namespace Ewll\DBBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * {@inheritdoc}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ewll_user');
        $treeBuilder->getRootNode()
            ->fixXmlConfig('shard')
            ->fixXmlConfig('connection')
            ->children()
                ->arrayNode('bundles')
                    ->prototype('scalar')
                        ->defaultValue([])
                    ->end()
                ->end()
                ->arrayNode('connections')
                    ->defaultValue([])
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('database')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('password')->isRequired()->defaultValue('')->end()
                            ->scalarNode('host')->cannotBeEmpty()->defaultValue('127.0.0.1')->end()
                            ->scalarNode('port')->cannotBeEmpty()->defaultValue('3306')->end()
                            ->scalarNode('charset')->cannotBeEmpty()->defaultValue('utf8mb4')->end()
                            ->scalarNode('cipherkey')->cannotBeEmpty()->defaultValue('')->end()
                            ->arrayNode('options')
                                ->prototype('integer')
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shards')
                    ->defaultValue([])
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('port')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('database')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('username')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('charset')->cannotBeEmpty()->defaultValue('utf8mb4')->end()
                                ->arrayNode('options')
                                    ->prototype('integer')
                                        ->defaultValue([])
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('logger')
                    ->children()
                        ->scalarNode('id')->isRequired()->defaultNull()->end()
                        ->scalarNode('channel')->isRequired()->defaultNull()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
