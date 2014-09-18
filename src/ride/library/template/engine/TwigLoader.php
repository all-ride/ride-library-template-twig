<?php

namespace ride\library\template\engine;

use ride\library\system\file\browser\FileBrowser;
use ride\library\template\exception\ResourceNotFoundException;

use \Twig_LoaderInterface;

/**
 * Default resource handler for Smarty according to the Zibo standards
 */
class TwigLoader implements Twig_LoaderInterface {

    /**
     * File browser to lookup the templates
     * @var \ride\library\system\file\browser\FileBrowser;
     */
    protected $fileBrowser;

    /**
     * Path for the file browser
     * @var string
     */
    protected $path;

    /**
     * Themes to use when looking for resources
     * @var array
     */
    protected $themes;

    /**
     * Id of the template
     * @var string
     */
    protected $templateId;

    /**
     * Constructs a new resource handler
     * @param \ride\library\system\file\browser\FileBrowser $fileBrowser File
     * browser to lookup the templates
     * @return null
     */
    public function __construct(FileBrowser $fileBrowser, $path = null) {
        $this->fileBrowser = $fileBrowser;

        $this->setPath($path);
    }

    /**
     * Sets the path for the file browser
     * @param string $path
     * @return null
     * @throws \ride\library\template\exception\TemplateException when the
     * provided path is invalid or empty
     */
    public function setPath($path) {
        if ($path !== null && (!is_string($path) || !$path)) {
            throw new TemplateException('Could not set the path for the file browser: provided path is empty or invalid');
        }

        $this->path = $path;
    }

    /**
     * Sets the themes used for looking the template resource
     * @param array $themes Array with the name of the themes as key
     * @return null
     * @throws \ride\library\template\exception\TemplateException when the
     * provided theme is invalid or empty
     */
    public function setThemes(array $themes = null) {
        $this->themes = $themes;
    }

    /**
     * Sets the id of the template. Don't forget to set compile_id on the
     * Smarty engine itself.
     * @param string $templateId Id of the template
     * @return null
     */
    public function setTemplateId($templateId) {
        $this->templateId = $templateId;
    }

    /**
     * Gets the source code of a template, given its name.
     * @param  string $name string The name of the template to load
     * @return string The template source code
     */
    public function getSource($name) {
        $templateFile = $this->getFile($name);

        return $templateFile->read();
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     * @param  string $name string The name of the template to load
     * @return string The cache key
     */
    public function getCacheKey($name) {
        $key = $name;

        if ($this->themes) {
            reset($this->themes);
            $theme = key($this->themes);

            $key = $theme . '-' . $key;
        }

        if ($this->templateId) {
            $key .= $this->templateId;
        }

        return $key;
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time) {
        $templateFile = $this->getFile($name);

        return $templateFile->getModificationTime() <= $time;
    }

    /**
     * Get the source file of a template
     * @param string $name Relative path of the template to the view folder
     * without the extension
     * @return \ride\library\system\file\File instance of a File if the source
     * is found, null otherwise
     */
    public function getFile($name) {
        $file = null;

        if ($this->themes) {
            foreach ($this->themes as $theme => $null) {
                try {
                    $file = $this->getThemeFile($name, $theme);

                    break;
                } catch (ResourceNotFoundException $exception) {
                    $file = null;
                }
            }
        }

        if (!$file) {
            $file = $this->getThemeFile($name);
        }

        return $file;
    }

    /**
     * Gets the source file of a template
     * @param string $name Relative path of the template to the view folder
     * without the extension
     * @return \ride\library\system\file\File Instance of a File if the source
     * is found, null otherwise
     */
    protected function getThemeFile($name, $theme = null) {
        $path = '';
        if ($theme) {
            $path = $theme . '/';
        }

        if ($this->path) {
            $path = $this->path . '/' . $path;
        }

        if ($path) {
            $path = rtrim($path, '/') . '/';
        }

        $file = null;

        if ($this->templateId) {
            $fileName = $path . $name . '.' . $this->templateId . '.' . TwigEngine::EXTENSION;

            $file = $this->fileBrowser->getFile($fileName);
        }

        if (!$file) {
            $fileName = $path . $name . '.' . TwigEngine::EXTENSION;

            $file = $this->fileBrowser->getFile($fileName);
        }

        if (!$file) {
            throw new ResourceNotFoundException($fileName);
        }

        return $file;
    }

    /**
     * Gets the available template resources for the provided namespace
     * @param string $namespace
     * @return array
     */
    public function getFiles($namespace) {
        $files = array();

        $basePath = '';
        if ($this->path) {
            $basePath = $this->path . '/';
        }

        if ($this->themes) {
            foreach ($this->themes as $theme => $null) {
                $path = $basePath . $theme . '/' . $namespace;

                $files += $this->getPathFiles($path, $basePath . $theme . '/');
            }
        } else {
            $path = $basePath . $namespace;

            $files += $this->getPathFiles($path, $basePath);
        }

        return $files;
    }

    /**
     * Gets the files for the provided path
     * @param string $path Relative path in the Ride file structure of the
     * requested files
     * @param string $basePath Relative path in the Ride file structure of the
     * engine and theme
     * @return array
     */
    protected function getPathFiles($path, $basePath) {
        $files = array();

        $pathDirectories = $this->fileBrowser->getFiles($path);
        if (!$pathDirectories) {
            return $files;
        }

        foreach ($pathDirectories as $pathDirectory) {
            $pathFiles = $pathDirectory->read();
            foreach ($pathFiles as $pathFile) {
                if ($pathFile->isDirectory() || $pathFile->getExtension() != TwigEngine::EXTENSION) {
                    continue;
                }

                $pathFile = $this->fileBrowser->getRelativeFile($pathFile);
                $filePath = $pathFile->getPath();
                $resultPath = substr(str_replace($basePath, '', $filePath), 0, (strlen(TwigEngine::EXTENSION) + 1) * -1);
                $resultName = substr(str_replace($path . '/', '', $filePath), 0, (strlen(TwigEngine::EXTENSION) + 1) * -1);

                $files[$resultPath] = $resultName;
            }
        }

        return $files;
    }

}
