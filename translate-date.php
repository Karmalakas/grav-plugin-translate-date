<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\TranslateDate\Twig\FilterTd;
use RuntimeException;

/**
 * Class TranslateDatePlugin
 *
 * @package Grav\Plugin
 */
class TranslateDatePlugin extends Plugin
{
    /**
     * @var  FilterTd $filter
     */
    protected $filter;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0],
            ],
        ];
    }

    /**
     * Composer autoload.
     *is
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if (
            $this->config->get('plugins.translate-date.enabled') === true
            && $this->config->get('plugins.translate-date.processor') === 'intl'
            && !class_exists('IntlDateFormatter')
        ) {
            throw new RuntimeException('The native PHP intl extension (http://php.net/manual/en/book.intl.php) is needed to use intl-based filters.');
        }

        $this->enable(
            [
                'onTwigInitialized' => ['onTwigInitialized', 0],
            ]
        );

        $this->filter = new FilterTd();
    }

    /**
     * Add a twig filter to translate dates in templates
     */
    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFilter(new \Twig_SimpleFilter('td', [$this->filter, 'translateDate']));
    }
}
