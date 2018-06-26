<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Console;

use allejo\stakx\AssetEngine\AssetEngine;
use allejo\stakx\DataTransformer\DataTransformer;
use allejo\stakx\MarkupEngine\MarkupEngine;
use allejo\stakx\Templating\Twig\Extension\TwigFilterInterface;
use allejo\stakx\Templating\Twig\Extension\TwigFunctionInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder as BaseBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

class ContainerBuilder
{
    private $containerPath;
    private $options;

    public function __construct(array $options)
    {
        $this->containerPath = __DIR__ . '/Container.php';
        $this->options = $options;
    }

    public function build()
    {
        if (!$this->isPhar())
        {
            $this->compileAndDump();
        }

        return $this->containerPath;
    }

    private function isPhar()
    {
        return strlen(\Phar::running()) > 0;
    }

    private function compileAndDump()
    {
        $container = new BaseBuilder();
        $container
            ->addCompilerPass(new RegisterListenersPass())
        ;

        foreach ($this->options['parameters'] as $key => $value)
        {
            $container->setParameter($key, $value);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/app/'));
        $loader->load('services.yml');

        $container
            ->registerForAutoconfiguration(AssetEngine::class)
            ->addTag(AssetEngine::CONTAINER_TAG)
        ;

        $container
            ->registerForAutoconfiguration(DataTransformer::class)
            ->addTag(DataTransformer::CONTAINER_TAG)
        ;

        $container
            ->registerForAutoconfiguration(MarkupEngine::class)
            ->addTag(MarkupEngine::CONTAINER_TAG)
        ;

        $container
            ->registerForAutoconfiguration(TwigFilterInterface::class)
            ->addTag(TwigFilterInterface::CONTAINER_TAG)
        ;

        $container
            ->registerForAutoconfiguration(TwigFunctionInterface::class)
            ->addTag(TwigFunctionInterface::CONTAINER_TAG)
        ;

        $container->compile();

        $dumper = new PhpDumper($container);
        file_put_contents($this->containerPath, $dumper->dump());
    }
}
