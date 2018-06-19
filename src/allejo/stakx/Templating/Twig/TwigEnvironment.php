<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Templating\Twig;

class TwigEnvironment extends \Twig_Environment
{
    /**
     * Clear the cache of loaded templates.
     *
     * @todo Only delete cached templates that are directly related
     *
     * @param string $templatePath
     */
    public function clearCachedTemplate($templatePath)
    {
        $this->loadedTemplates = [];

        /** @var \Twig_Template $template */
        foreach ($this->loadedTemplates as $template)
        {
            if ($template->getTemplateName() === $templatePath)
            {
                unset($this->loadedTemplates[get_class($template)]);
                break;
            }
        }
    }
}
