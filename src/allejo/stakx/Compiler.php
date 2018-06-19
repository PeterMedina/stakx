<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx;

use allejo\stakx\Command\BuildableCommand;
use allejo\stakx\Document\BasePageView;
use allejo\stakx\Document\ContentItem;
use allejo\stakx\Document\DynamicPageView;
use allejo\stakx\Document\PermalinkDocument;
use allejo\stakx\Document\RepeaterPageView;
use allejo\stakx\Document\StaticPageView;
use allejo\stakx\Document\TemplateReadyDocument;
use allejo\stakx\Event\CompileProcessPostRenderPageView;
use allejo\stakx\Event\CompileProcessPreRenderPageView;
use allejo\stakx\Event\CompileProcessTemplateCreation;
use allejo\stakx\Exception\FileAwareException;
use allejo\stakx\Filesystem\FilesystemLoader as fs;
use allejo\stakx\Filesystem\FilesystemPath;
use allejo\stakx\Filesystem\Folder;
use allejo\stakx\FrontMatter\ExpandedValue;
use allejo\stakx\Manager\PageManager;
use allejo\stakx\Manager\ThemeManager;
use allejo\stakx\Templating\TemplateBridgeInterface;
use allejo\stakx\Templating\TemplateErrorInterface;
use allejo\stakx\Templating\TemplateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * This class takes care of rendering the Twig body of PageViews with the respective information and it also takes care
 * of writing the rendered Twig to the filesystem.
 *
 * @internal
 *
 * @since 0.1.1
 */
class Compiler
{
    /** @var string|false */
    private $redirectTemplate;

    /** @var BasePageView[][] */
    private $importDependencies;

    /**
     * Any time a PageView extends another template, that relationship is stored in this array. This is necessary so
     * when watching a website, we can rebuild the necessary PageViews when these base templates change.
     *
     * ```
     * array['_layouts/base.html.twig'] = &PageView;
     * ```
     *
     * @var TemplateInterface[]
     */
    private $templateDependencies;

    /**
     * All of the PageViews handled by this Compiler instance indexed by their file paths relative to the site root.
     *
     * ```
     * array['_pages/index.html.twig'] = &PageView;
     * ```
     *
     * @var BasePageView[]
     */
    private $pageViewsFlattened;

    /** @var string[] */
    private $templateMapping;

    /** @var Folder */
    private $folder;

    /** @var string */
    private $theme;

    private $templateBridge;
    private $pageManager;
    private $eventDispatcher;
    private $configuration;

    public function __construct(TemplateBridgeInterface $templateBridge, Configuration $configuration, PageManager $pageManager, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->templateBridge = $templateBridge;
        $this->theme = '';
        $this->pageManager = $pageManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->configuration = $configuration;

        $this->pageViewsFlattened = &$pageManager->getPageViewsFlattened();
        $this->redirectTemplate = $this->configuration->getRedirectTemplate();
    }

    /**
     * @param Folder $folder
     */
    public function setTargetFolder(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * @param string $themeName
     */
    public function setThemeName($themeName)
    {
        $this->theme = $themeName;
    }

    /**
     * Get the Template Bridge used by this compiler.
     *
     * @return TemplateBridgeInterface
     */
    public function getTemplateBridge()
    {
        return $this->templateBridge;
    }

    ///
    // Twig parent templates
    ///

    public function isImportDependency($filePath)
    {
        return isset($this->importDependencies[$filePath]);
    }

    /**
     * Check whether a given file path is used as a parent template by a PageView.
     *
     * @param string $filePath
     *
     * @return bool
     */
    public function isParentTemplate($filePath)
    {
        return isset($this->templateDependencies[$filePath]);
    }

    /**
     * Rebuild all of the PageViews that used a given template as a parent.
     *
     * @param string $filePath The file path to the parent Twig template
     */
    public function refreshParent($filePath)
    {
        foreach ($this->templateDependencies[$filePath] as &$parentTemplate)
        {
            $this->compilePageView($parentTemplate);
        }
    }

    public function getTemplateMappings()
    {
        return $this->templateMapping;
    }

    ///
    // IO Functionality
    ///

    /**
     * Compile all of the PageViews registered with the compiler.
     *
     * @since 0.1.0
     */
    public function compileAll()
    {
        foreach ($this->pageViewsFlattened as &$pageView)
        {
            $this->compilePageView($pageView);
        }
    }

    public function compileImportDependencies($filePath)
    {
        foreach ($this->importDependencies[$filePath] as &$dependent)
        {
            $this->compilePageView($dependent);
        }
    }

    public function compileSome(array $filter = [])
    {
        /** @var BasePageView $pageView */
        foreach ($this->pageViewsFlattened as &$pageView)
        {
            $ns = $filter['namespace'];

            if ($pageView->hasDependencyOnCollection($ns, $filter['dependency']) ||
                $pageView->hasDependencyOnCollection($ns, null)
            ) {
                $this->compilePageView($pageView);
            }
        }
    }

    /**
     * Compile an individual PageView item.
     *
     * This function will take care of determining *how* to treat the PageView and write the compiled output to a the
     * respective target file.
     *
     * @param DynamicPageView|RepeaterPageView|StaticPageView $pageView The PageView that needs to be compiled
     *
     * @since 0.1.1
     */
    public function compilePageView(BasePageView &$pageView)
    {
        $this->templateBridge->setGlobalVariable('__currentTemplate', $pageView->getAbsoluteFilePath());
        $this->logger->debug('Compiling {type} PageView: {pageview}', [
            'pageview' => $pageView->getRelativeFilePath(),
            'type' => $pageView->getType(),
        ]);

        try
        {
            switch ($pageView->getType())
            {
                case BasePageView::STATIC_TYPE:
                    $this->compileStaticPageView($pageView);
                    $this->compileStandardRedirects($pageView);
                    break;

                case BasePageView::DYNAMIC_TYPE:
                    $this->compileDynamicPageView($pageView);
                    $this->compileStandardRedirects($pageView);
                    break;

                case BasePageView::REPEATER_TYPE:
                    $this->compileRepeaterPageView($pageView);
                    $this->compileExpandedRedirects($pageView);
                    break;
            }
        }
        catch (TemplateErrorInterface $e)
        {
            throw new FileAwareException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                $pageView->getRelativeFilePath(),
                $e->getTemplateLine() + $pageView->getLineOffset()
            );
        }
    }

    /**
     * Write the compiled output for a static PageView.
     *
     * @since 0.1.1
     *
     * @throws TemplateErrorInterface
     */
    private function compileStaticPageView(StaticPageView &$pageView)
    {
        $pageView->compile();

        $this->writeToFilesystem(
            $pageView->getTargetFile(),
            $this->renderStaticPageView($pageView),
            BasePageView::STATIC_TYPE
        );
    }

    /**
     * Write the compiled output for a dynamic PageView.
     *
     * @param DynamicPageView $pageView
     *
     * @since 0.1.1
     *
     * @throws TemplateErrorInterface
     */
    private function compileDynamicPageView(DynamicPageView &$pageView)
    {
        $contentItems = $pageView->getCollectableItems();
        $template = $this->createTwigTemplate($pageView);

        foreach ($contentItems as &$contentItem)
        {
            if ($contentItem->isDraft() && !Service::getParameter(BuildableCommand::USE_DRAFTS))
            {
                $this->logger->debug('{file}: marked as a draft', [
                    'file' => $contentItem->getRelativeFilePath(),
                ]);

                continue;
            }

            $this->writeToFilesystem(
                $contentItem->getTargetFile(),
                $this->renderDynamicPageView($template, $contentItem),
                BasePageView::DYNAMIC_TYPE
            );

            $this->compileStandardRedirects($contentItem);
        }
    }

    /**
     * Write the compiled output for a repeater PageView.
     *
     * @param RepeaterPageView $pageView
     *
     * @since 0.1.1
     *
     * @throws TemplateErrorInterface
     */
    private function compileRepeaterPageView(RepeaterPageView &$pageView)
    {
        $pageView->rewindPermalink();

        $template = $this->createTwigTemplate($pageView);
        $permalinks = $pageView->getRepeaterPermalinks();

        foreach ($permalinks as $permalink)
        {
            $pageView->bumpPermalink();

            $this->writeToFilesystem(
                $pageView->getTargetFile(),
                $this->renderRepeaterPageView($template, $pageView, $permalink),
                BasePageView::REPEATER_TYPE
            );
        }
    }

    /**
     * Write the given $output to the $targetFile as a $fileType PageView.
     *
     * @param string $targetFile
     * @param string $output
     * @param string $fileType
     */
    private function writeToFilesystem($targetFile, $output, $fileType)
    {
        $this->logger->notice('Writing {type} PageView file: {file}', [
            'type' => $fileType,
            'file' => $targetFile,
        ]);
        $this->folder->writeFile($targetFile, $output);
    }

    /**
     * @deprecated
     *
     * @todo This function needs to be rewritten or removed. Something
     *
     * @param ContentItem $contentItem
     */
    public function compileContentItem(ContentItem &$contentItem)
    {
        $pageView = &$contentItem->getPageView();
        $template = $this->createTwigTemplate($pageView);

        $this->templateBridge->setGlobalVariable('__currentTemplate', $pageView->getAbsoluteFilePath());
        $contentItem->evaluateFrontMatter($pageView->getFrontMatter(false));

        $targetFile = $contentItem->getTargetFile();
        $output = $this->renderDynamicPageView($template, $contentItem);

        $this->logger->notice('Writing file: {file}', ['file' => $targetFile]);
        $this->folder->writeFile($targetFile, $output);
    }

    ///
    // Runtime Methods
    ///

    /**
     * Compile an individual PageView from a given path.
     *
     * This method is designed to be used at runtime meaning it will refresh the content of the PageView.
     *
     * @param string $filePath
     *
     * @throws FileNotFoundException when the given file path isn't tracked by the Compiler
     */
    public function runtimeCompilePageViewFromPath($filePath)
    {
        if (!isset($this->pageViewsFlattened[$filePath]))
        {
            throw new FileNotFoundException(sprintf('The "%s" PageView is not being tracked by this compiler.', $filePath));
        }

        $pageView = &$this->pageViewsFlattened[$filePath];
        $pageView->readContent();

        $this->compilePageView($pageView);
    }

    /**
     * Compile a CollectableItem based on its path if it is being tracked.
     *
     * @param string $filePath
     *
     * @return bool If the CollectableItem was found in any of the DynamicPageViews
     */
    public function runtimeCompileCollectableItemFromPath($filePath)
    {
        $pageViews = &$this->pageManager->getPageViews();
        $dynamicPageViews = $pageViews[BasePageView::DYNAMIC_TYPE];

        /** @var DynamicPageView $pageView */
        foreach ($dynamicPageViews as $pageView)
        {
            $collectableItem = $pageView->getCollectableItem($filePath);

            if ($collectableItem !== null)
            {
                $this->writeToFilesystem(
                    $collectableItem->getTargetFile(),
                    $this->renderDynamicPageView($template, $collectableItem),
                    BasePageView::DYNAMIC_TYPE
                );

                return true;
            }
        }

        return false;
    }

    ///
    // Redirect handling
    ///

    /**
     * Write redirects for standard redirects.
     *
     * @throws TemplateErrorInterface
     *
     * @since 0.1.1
     */
    private function compileStandardRedirects(PermalinkDocument &$pageView)
    {
        $redirects = $pageView->getRedirects();

        foreach ($redirects as $redirect)
        {
            $redirectPageView = BasePageView::createRedirect(
                $redirect,
                $pageView->getPermalink(),
                $this->redirectTemplate
            );
            $redirectPageView->evaluateFrontMatter([], [
                'site' => $this->configuration->getConfiguration(),
            ]);

            $this->compileStaticPageView($redirectPageView);
        }
    }

    /**
     * Write redirects for expanded redirects.
     *
     * @param RepeaterPageView $pageView
     *
     * @since 0.1.1
     */
    private function compileExpandedRedirects(RepeaterPageView &$pageView)
    {
        $permalinks = $pageView->getRepeaterPermalinks();

        /** @var ExpandedValue[] $repeaterRedirect */
        foreach ($pageView->getRepeaterRedirects() as $repeaterRedirect)
        {
            /**
             * @var int
             * @var ExpandedValue $redirect
             */
            foreach ($repeaterRedirect as $index => $redirect)
            {
                $redirectPageView = BasePageView::createRedirect(
                    $redirect->getEvaluated(),
                    $permalinks[$index]->getEvaluated(),
                    $this->redirectTemplate
                );
                $redirectPageView->evaluateFrontMatter([], [
                    'site' => $this->configuration->getConfiguration(),
                ]);

                $this->compilePageView($redirectPageView);
            }
        }
    }

    ///
    // Twig Functionality
    ///

    /**
     * Get the compiled HTML for a specific iteration of a repeater PageView.
     *
     * @param TemplateInterface $template
     * @param RepeaterPageView  $pageView
     * @param ExpandedValue     $expandedValue
     *
     * @since  0.1.1
     *
     * @return string
     */
    private function renderRepeaterPageView(TemplateInterface &$template, RepeaterPageView &$pageView, ExpandedValue &$expandedValue)
    {
        $defaultContext = [
            'this' => $pageView->createJail(),
        ];

        $pageView->evaluateFrontMatter([
            'permalink' => $expandedValue->getEvaluated(),
            'iterators' => $expandedValue->getIterators(),
        ]);

        $preEvent = new CompileProcessPreRenderPageView(BasePageView::REPEATER_TYPE);
        $this->eventDispatcher->dispatch(CompileProcessPreRenderPageView::NAME, $preEvent);

        $context = array_merge($preEvent->getCustomVariables(), $defaultContext);
        $output = $template
            ->render($context)
        ;

        $postEvent = new CompileProcessPostRenderPageView(BasePageView::REPEATER_TYPE, $output);
        $this->eventDispatcher->dispatch(CompileProcessPostRenderPageView::NAME, $postEvent);

        return $postEvent->getCompiledOutput();
    }

    /**
     * Get the compiled HTML for a specific ContentItem.
     *
     * @since  0.1.1
     *
     * @return string
     */
    private function renderDynamicPageView(TemplateInterface &$template, TemplateReadyDocument &$twigItem)
    {
        $defaultContext = [
            'this' => $twigItem->createJail(),
        ];

        $preEvent = new CompileProcessPreRenderPageView(BasePageView::DYNAMIC_TYPE);
        $this->eventDispatcher->dispatch(CompileProcessPreRenderPageView::NAME, $preEvent);

        $content = array_merge($preEvent->getCustomVariables(), $defaultContext);
        $output = $template
            ->render($content)
        ;

        $postEvent = new CompileProcessPostRenderPageView(BasePageView::DYNAMIC_TYPE, $output);
        $this->eventDispatcher->dispatch(CompileProcessPostRenderPageView::NAME, $postEvent);

        return $postEvent->getCompiledOutput();
    }

    /**
     * Get the compiled HTML for a static PageView.
     *
     * @since  0.1.1
     *
     * @throws TemplateErrorInterface
     *
     * @return string
     */
    private function renderStaticPageView(StaticPageView &$pageView)
    {
        $defaultContext = [
            'this' => $pageView->createJail(),
        ];

        $preEvent = new CompileProcessPreRenderPageView(BasePageView::STATIC_TYPE);
        $this->eventDispatcher->dispatch(CompileProcessPreRenderPageView::NAME, $preEvent);

        $context = array_merge($preEvent->getCustomVariables(), $defaultContext);
        $output = $this
            ->createTwigTemplate($pageView)
            ->render($context)
        ;

        $postEvent = new CompileProcessPostRenderPageView(BasePageView::STATIC_TYPE, $output);
        $this->eventDispatcher->dispatch(CompileProcessPostRenderPageView::NAME, $postEvent);

        return $postEvent->getCompiledOutput();
    }

    /**
     * Create a Twig template that just needs an array to render.
     *
     * @since  0.1.1
     *
     * @throws TemplateErrorInterface
     *
     * @return TemplateInterface
     */
    private function createTwigTemplate(BasePageView &$pageView)
    {
        try
        {
            $template = $this->templateBridge->createTemplate($pageView->getContent());

            $this->templateMapping[$template->getTemplateName()] = $pageView->getRelativeFilePath();

            $event = new CompileProcessTemplateCreation($pageView, $template, $this->theme);
            $this->eventDispatcher->dispatch(CompileProcessTemplateCreation::NAME, $event);

            return $template;
        }
        catch (TemplateErrorInterface $e)
        {
            $e
                ->setTemplateLine($e->getTemplateLine() + $pageView->getLineOffset())
                ->setContent($pageView->getContent())
                ->setName($pageView->getRelativeFilePath())
                ->setRelativeFilePath($pageView->getRelativeFilePath())
                ->buildException()
            ;

            throw $e;
        }
    }
}
