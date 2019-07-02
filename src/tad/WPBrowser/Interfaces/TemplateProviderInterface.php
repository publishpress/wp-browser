<?php
/**
 * The API provided by any object providing template functions wrapping an Handlebar object.
 *
 * @package tad\WPBrowser\Generators
 */

namespace tad\WPBrowser\Interfaces;

use Handlebars\Handlebars;

/**
 * Interface TemplateProviderInterface
 *
 * @package tad\WPBrowser\Generators
 */
interface TemplateProviderInterface
{

    /**
     * TemplateProviderInterface constructor.
     *
     * @param Handlebars $handlebars An Handlebars template engine instance.
     * @param array      $data The data to use for the replacement.
     */
    public function __construct(Handlebars $handlebars, array $data = [ ]);

    public function getContents();
}
