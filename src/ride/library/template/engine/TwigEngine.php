<?php

namespace ride\library\template\engine;

use ride\library\template\Template;
use ride\library\template\ThemedTemplate;
use ride\library\system\file\File;

use \Twig_Environment;
use \Twig_Extension;

/**
 * Twig implementation for a template engine
 */
class TwigEngine extends AbstractEngine {

    /**
     * Name of this engine
     * @var string
     */
    const NAME = 'twig';

    /**
     * Extension for resources of this engine
     * @var string
     */
    const EXTENSION = 'twig';

    /**
     * Tag to open a block comment
     * @var string
     */
    const COMMENT_OPEN = '{#';

    /**
     * Tag to close a block comment
     * @var string
     */
    const COMMENT_CLOSE = '#}';

    /**
     * Instance of the Twig engine
     * @var Twig_Environment
     */
    protected $twig;

    /**
     * Implementation of the resource handler
     * @var TwigLoader
     */
    protected $loader;

    /**
     * Constructs a new Smarty template engine
     * @param \ride\library\template\TwigLoader $loader Resource handler for
     * the template engine
     * @param \ride\library\system\file\File $compileDirectory Directory for
     * the compiled templates
     * @return null
     */
    public function __construct(TwigLoader $loader, File $compileDirectory) {
        $compileDirectory->create();

        $this->loader = $loader;
        $this->twig = new Twig_Environment($this->loader, array(
            'cache' => $compileDirectory->getPath(),
            'auto_reload' => true,
        ));
    }

    /**
     * Gets the instance of Twig
     * @return Twig_Environment
     */
    public function getTwig() {
        return $this->twig;
    }

    /**
     * Adds an extension to the Twig engine
     * @param Twig_Extension $extension
     * @return null
     */
    public function addExtension(Twig_Extension $extension) {
        $this->twig->addExtension($extension);
    }

    /**
     * Renders a template
     * @param \ride\library\template\Template $template Template to render
     * @return string Rendered template
     * @throws \ride\library\template\exception\ResourceNotSetException when
     * no template resource was set to the template
     * @throws \ride\library\template\exception\ResourceNotFoundException when
     * the template resource could not be found by the engine
     */
    public function render(Template $template) {
        $resource = $template->getResource();
        if (!$resource) {
            throw new ResourceNotSetException();
        }

        $this->preProcess($template);

        try {
            $output = $this->twig->render($resource, $template->getVariables());

            $exception = null;
        } catch (Exception $e) {
            ob_get_clean();

            $exception = $e;
        }

        $this->postProcess();

        if ($exception) {
            throw $exception;
        }

        return $output;
    }

    /**
     * Gets the template resource
     * @param \ride\library\template\Template $template Template to get the
     * resource of
     * @return \ride\library\system\file\File $file File instance for the
     * template resource
     * @throws \ride\library\template\exception\ResourceNotSetException when
     * no template was set to the template
     * @throws \ride\library\template\exception\ResourceNotFoundException when
     * the template could not be found by the engine
     */
    public function getFile(Template $template) {
        $resource = $template->getResource();
        if (!$resource) {
            throw new ResourceNotSetException();
        }

        $this->preProcess($template);

        return $this->loader->getFile($resource);
    }

    /**
     * Gets the available template resources for the provided namespace
     * @param string $namespace
     * @param string $theme
     * @return array Array with the relative path of the resource as key and the
     * name as value
     */
    public function getFiles($namespace, $theme = null) {
        $theme = $this->themeModel->getTheme($theme);
        $themeHierarchy = $this->getThemeHierarchy($theme);

        $this->loader->setThemes($themeHierarchy);

        $files = $this->loader->getFiles($namespace);

        $this->postProcess();

        return $files;
    }

    /**
     * Preprocess this engine before performing a template action
     * @param \ride\library\template\Template $template
     * @return null
     */
    protected function preProcess(Template $template) {
        if (!$template instanceof ThemedTemplate) {
            return;
        }

        $themeHierarchy = $this->getTheme($template);

        $this->loader->setThemes($themeHierarchy);

        $templateId = $template->getResourceId();
        if ($templateId) {
            $this->loader->setTemplateId($templateId);
        }
    }

    /**
     * Postprocess this engine after performing a template action
     * @return null
     */
    protected function postProcess() {
        $this->loader->setThemes(null);
        $this->loader->setTemplateId(null);
    }

}
