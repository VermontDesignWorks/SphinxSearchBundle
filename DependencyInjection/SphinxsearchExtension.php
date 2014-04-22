<?php

namespace Vdw\SphinxSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SphinxsearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('sphinxsearch.xml');

        /**
         * Indexer.
         */
        if( isset($config['indexer']) ) {
            $container->setParameter('vdw.sphinxsearch.indexer.bin', $config['indexer']['bin']);
        }

        /**
         * Indexes.
         */
        $container->setParameter('vdw.sphinxsearch.indexes', $config['indexes']);

        /**
         * Searchd.
         */
        if( isset($config['searchd']) ) {
            $container->setParameter('vdw.sphinxsearch.searchd.host', $config['searchd']['host']);
            $container->setParameter('vdw.sphinxsearch.searchd.port', $config['searchd']['port']);
            $container->setParameter('vdw.sphinxsearch.searchd.socket', $config['searchd']['socket']);
        }
    }

    public function getAlias()
    {
        return 'sphinxsearch';
    }
}
