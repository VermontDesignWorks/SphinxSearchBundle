<?php

namespace Vdw\SphinxSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SphinxSearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        if (isset($config['indexer'])) {
            $container->setParameter('vdw.sphinx_search.indexer.bin', $config['indexer']['bin']);
        }

        if (isset($config['searchd'])) {
            $container->setParameter('vdw.sphinx_search.searchd.host', $config['searchd']['host']);
            $container->setParameter('vdw.sphinx_search.searchd.port', $config['searchd']['port']);
            $container->setParameter('vdw.sphinx_search.searchd.socket', $config['searchd']['socket']);
        }

        $container->setParameter('vdw.sphinx_search.indexes', $config['indexes']);
    }

    public function getAlias()
    {
        return 'sphinx_search';
    }
}
