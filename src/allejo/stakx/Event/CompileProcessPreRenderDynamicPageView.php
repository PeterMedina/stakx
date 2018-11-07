<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Event;

use allejo\stakx\Document\BasePageView;
use allejo\stakx\Document\CollectableItem;
use Symfony\Component\EventDispatcher\Event;

/**
 * This event is fired before a Dynamic PageView is rendered and allows you inject custom variables into the templates.
 *
 * @since 0.2.0
 */
class CompileProcessPreRenderDynamicPageView extends Event
{
    use CompileProcessPreRenderTrait;

    const NAME = 'compile.process.prerender_dynamic_pageview';

    private $collectableItem;

    public function __construct(CollectableItem $collectable)
    {
        $this->collectableItem = $collectable;
    }

    public function getCollectableItem()
    {
        return clone $this->collectableItem;
    }
}
