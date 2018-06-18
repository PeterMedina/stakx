<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\EventSubscriber;

use allejo\stakx\Compiler;
use allejo\stakx\Core\StakxLogger;
use allejo\stakx\FileMapper;
use allejo\stakx\Filesystem\FilesystemLoader as fs;
use Kwf\FileWatcher\Event\Modify as ModifyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileModificationSubscriber implements EventSubscriberInterface
{
    private $fileMapper;
    private $compiler;
    private $logger;

    public function __construct(Compiler $compiler, FileMapper $fileMapper, StakxLogger $logger)
    {
        $this->fileMapper = $fileMapper;
        $this->compiler = $compiler;
        $this->logger = $logger;
    }

    public function onFileModification(ModifyEvent $event)
    {
        $relFilePath = fs::getRelativePath($event->filename);
        $this->logger->writeln(sprintf('File change detected: %s', $relFilePath));

        switch ($this->fileMapper->getFileType($relFilePath))
        {
            case FileMapper::STATIC_PAGEVIEW:
            case FileMapper::DYNAMIC_PAGEVIEW:
            case FileMapper::REPEATER_PAGEVIEW:
            {
                $this->compiler->runtimeCompilePageViewFromPath($relFilePath);
            }
            break;

            case FileMapper::DATA_ITEM:
            case FileMapper::CONTENT_ITEM:
            {
                $this->compiler->runtimeCompileCollectableItemFromPath($relFilePath);
            }
            break;

            case FileMapper::TWIG_INCLUDE:
            {
                $dependents = $this->fileMapper->getTemplateDependents($relFilePath);

                foreach ($dependents as $dependent)
                {
                    $this->compiler->runtimeCompilePageViewFromPath($dependent);
                }
            }
            break;
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            ModifyEvent::NAME => 'onFileModification'
        ];
    }
}
