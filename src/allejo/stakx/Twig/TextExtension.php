<?php

namespace allejo\stakx\Twig;

use allejo\stakx\Utilities\HtmlUtils;
use Twig_Environment;
use Twig_Extension;
use Twig_SimpleFilter;

/**
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Henrik Bjornskov <hb@peytz.dk>
 */
class TextExtension extends Twig_Extension
{
    /**
     * Returns a list of filters.
     *
     * @return Twig_SimpleFilter[]
     */
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('summary', array($this, 'twig_summary_filter')),
            new Twig_SimpleFilter('truncate', array($this, 'twig_truncate_filter'), array('needs_environment' => true)),
            new Twig_SimpleFilter('wordwrap', array($this, 'twig_wordwrap_filter'), array('needs_environment' => true)),
        );
    }

    /**
     * Name of this extension.
     *
     * @return string
     */
    public function getName()
    {
        return 'Text';
    }

    public function twig_summary_filter($value, $paragraphCount = 1)
    {
        if (!extension_loaded('dom'))
        {
            @trigger_error('The DOM Extension is not loaded and is necessary for the "summary" Twig filter.', E_WARNING);
            return $value;
        }

        $dom = new \DOMDocument();
        $paragraphs = HtmlUtils::htmlXPath($dom, $value, sprintf('//body/p[position() <= %d]', $paragraphCount));

        $summary = '';

        foreach ($paragraphs as $paragraph)
        {
            $summary .= $dom->saveHTML($paragraph);
        }

        return $summary;
    }

    public function twig_truncate_filter(Twig_Environment $env, $value, $length = 30, $preserve = false, $separator = '...')
    {
        if (mb_strlen($value, $env->getCharset()) > $length)
        {
            if ($preserve)
            {
                // If breakpoint is on the last word, return the value without separator.
                if (false === ($breakpoint = mb_strpos($value, ' ', $length, $env->getCharset())))
                {
                    return $value;
                }

                $length = $breakpoint;
            }

            return rtrim(mb_substr($value, 0, $length, $env->getCharset())) . $separator;
        }

        return $value;
    }

    public function twig_wordwrap_filter(Twig_Environment $env, $value, $length = 80, $separator = "\n", $preserve = false)
    {
        $sentences = array();

        $previous = mb_regex_encoding();
        mb_regex_encoding($env->getCharset());

        $pieces = mb_split($separator, $value);
        mb_regex_encoding($previous);

        foreach ($pieces as $piece)
        {
            while (!$preserve && mb_strlen($piece, $env->getCharset()) > $length)
            {
                $sentences[] = mb_substr($piece, 0, $length, $env->getCharset());
                $piece = mb_substr($piece, $length, 2048, $env->getCharset());
            }

            $sentences[] = $piece;
        }

        return implode($separator, $sentences);
    }
}
