<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Filesystem;

/**
 * A representation of a file on a given filesystem.
 *
 * This class extends \SplFileInfo and adds new methods along with overriding some methods solely because I feel that
 * some of the naming can be misleading.
 *
 * @since 0.2.0
 */
final class File extends \SplFileInfo
{
    private $relativeParentFolder;
    private $relativeFilePath;

    /**
     * Constructor.
     *
     * @param string $filePath             The file name or absolute file path. If just a file name is given, then it
     *                                     will look for the file in the current working directory.
     * @param string $relativeParentFolder The relative path
     * @param string $relativeFilePath     The relative path name
     *
     * @since 0.2.0
     */
    public function __construct($filePath, $relativeParentFolder, $relativeFilePath)
    {
        parent::__construct($filePath);

        $this->relativeParentFolder = $relativeParentFolder;
        $this->relativeFilePath = $relativeFilePath;
    }

    /**
     * Get the name of the file without an extension.
     *
     * @param  null $suffix This value will be discarded and is only needed to be able to override the \SplFileInfo
     *                      definition.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getBasename($suffix = null)
    {
        return parent::getBasename('.' . $this->getExtension());
    }

    /**
     * Get the name of the with the extension.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getFilename()
    {
        return parent::getBasename();
    }

    /**
     * Get the absolute path to this file.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getPathname();
    }

    /**
     * Get the path to the parent folder of this file.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getParentFolder()
    {
        return $this->getPath();
    }

    /**
     * Get the file path to this file, relative to where it was created; likely the current working directory.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getRelativeFilePath()
    {
        return $this->relativeFilePath;
    }

    /**
     * Get the path to the parent folder this file, relative to where it was created; likely the current working directory.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function getRelativeParentFolder()
    {
        return $this->relativeParentFolder;
    }

    /**
     * Get the contents of this file.
     *
     * @since 0.2.0
     *
     * @throws \RuntimeException When the file could not be read.
     *
     * @return string
     */
    public function getContents()
    {
        $content = file_get_contents($this->getFilePath());

        if ($content === false)
        {
            $error = error_get_last();
            throw new \RuntimeException($error['message']);
        }

        return $content;
    }
}
