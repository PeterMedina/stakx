<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Document;

use allejo\stakx\Filesystem\File;
use allejo\stakx\FrontMatter\ExpandedValue;

class RepeaterPageView extends BasePageView implements TemplateReadyDocument
{
    /** @var \ArrayIterator An iterator for the permalinks used in order for this entity to be treated as a static PageView. */
    private $permalinksIterator;

    /** @var ExpandedValue[] All of the expanded permalinks. */
    private $permalinks;

    /** @var ExpandedValue[][] All of expanded redirects that should point to the respective permalink; this is estimated by index. */
    private $redirectLinks;

    /**
     * RepeaterPageView constructor.
     */
    public function __construct(File $file)
    {
        parent::__construct($file);

        $this->type = BasePageView::REPEATER_TYPE;
    }

    /**
     * Get the expanded values for the permalinks to this PageView.
     *
     * @return ExpandedValue[]
     */
    public function getRepeaterPermalinks()
    {
        return $this->permalinks;
    }

    /**
     * Get the expanded values for the redirects pointing to this PageView.
     *
     * @return ExpandedValue[][]
     */
    public function getRepeaterRedirects()
    {
        return $this->redirectLinks;
    }

    /**
     * When looping through permalinks in a RepeaterPageView, the permalink needs to be updated each time so that it may
     * behave as a static PageView.
     */
    public function bumpPermalink()
    {
        $this->permalink = $this->permalinksIterator->current()->getEvaluated();
        $this->permalinksIterator->next();
    }

    /**
     * Rewind the permalink iterator to the beginning.
     */
    public function rewindPermalink()
    {
        $this->permalinksIterator->rewind();
    }

    /**
     * Configure permalinks from expanded values internally.
     */
    public function configurePermalinks()
    {
        $evaluated = $this->frontMatter['permalink'];

        $this->permalinks = $evaluated[0];
        array_shift($evaluated);
        $this->redirectLinks = $evaluated;

        $this->permalinksIterator = new \ArrayIterator($this->permalinks);
    }

    /**
     * {@inheritdoc}
     */
    public function buildPermalink($force = false)
    {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function createJail()
    {
        return (new JailedDocument($this, self::$whiteListedFunctions));
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [];
    }
}
