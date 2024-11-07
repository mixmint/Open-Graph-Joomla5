<?php
/**
* @version 1.2
* @package Menu and Article Open Graph parameters plugin
* @author Mirosław Majka (mix@proask.pl)
* @copyright Copyright 2024
* @license GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
**/

namespace Joomla\Plugin\System\OpenGraph\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

final class OpenGraph extends CMSPlugin
{
    protected $app;
    private $doc;
    private $config;
    private $metaArticleOg = [];
    private $metaMenuOg = [];
    private $metaPluginOg = [];

    public function onContentPrepareForm(Form $form, $data): bool
    {
        $name = $form->getName();

        if (!\in_array($name, ['com_content.article', 'com_menus.item'])) {
            return true;
        }

        $this->loadLanguage();

        FormHelper::addFieldPrefix('Joomla\\Plugin\\System\\OpenGraph\\Field');
        FormHelper::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');

        switch ($name) {
            case 'com_menus.item':
                $form->loadFile('parameters-menu', false);
                break;

            case 'com_content.article':
                $form->loadFile('parameters', false);
                break;
        }

        return true;
    }

    public function onBeforeRender()
    {
        $this->app    = Factory::getApplication();
        $this->config = $this->app->getConfig();
        $this->doc    = $this->app->getDocument();
        $id           = $this->app->input->get('view') == 'article' ? $this->app->input->get('id') : null;

        if ($this->app->isClient('site')) {
            $model = $this->app->bootComponent($this->app->input->get('option'))->getMVCFactory()->createModel('Article', 'Site');
            $menu  = $this->app->getMenu()->getActive();

            $this->getOg('article', $model, $id);
            $this->getOg('menu', $menu);
            $this->getOg('plugin');

            switch (true) {
                case (!$this->params->get('hierarchy') && !empty($this->metaMenuOg) && !empty($this->metaArticleOg)) || (!empty($this->metaMenuOg) && empty($this->metaArticleOg)):
                    $this->setMetaTags($this->metaMenuOg);
                    break;

                case empty($this->metaMenuOg) && !empty($this->metaArticleOg):
                case $this->params->get('hierarchy') && !empty($this->metaMenuOg) && !empty($this->metaArticleOg):
                    $this->setMetaTags($this->metaArticleOg);
                    break;

                case empty($this->metaMenuOg) && empty($this->metaArticleOg):
                    $this->setMetaTags($this->metaPluginOg);
                    break;
            }
        }
    }

    private function getOg(string $method, $element = null, int $id = null)
    {
        switch ($method) {
            case 'article':
                if (false == $element || null == $id) {
                    return;
                }

                $selector  = 'metaArticleOg';
                $article   = $element->getItem($id);
                $element   = json_decode($article->attribs, false);
                $introtext = strtok(
                    wordwrap(
                        preg_replace('/\s+/', ' ', strip_tags($article->introtext)), 200, "...\n"
                    ), "\n"
                );

                $ogTitle       = !empty($element->og_title) ? $element->og_title : $article->title;
                $ogDescription = (!empty($element->og_description) ? $element->og_description : null)
                ?? (!empty($article->metadesc) ? $article->metadesc : null)
                ?? (!empty($introtext) ? $introtext : null)
                ?? $this->params->get('og_description');
                break;

            case 'menu':
                $selector      = 'metaMenuOg';
                $menuItem      = $element;
                $element       = $element->getParams();
                $ogTitle       = !empty($element->get('og_title')) ? $element->get('og_title') : $menuItem->title;
                $ogDescription = $element->get('og_description')
                ?? (!empty($element->get('menu-meta_description')) ? $element->get('menu-meta_description') : null)
                ?? $this->params->get('og_description');
                break;

            case 'plugin':
                $selector      = 'metaPluginOg';
                $element       = $this->params;
                $ogTitle       = !empty($element->get('og_title')) ? $element->get('og_title') : $this->config->get('sitename');
                $ogDescription = !empty($element->get('og_description')) ? $element->get('og_description') : $this->config->get('MetaDesc');
                break;
        }

        if ($method == 'article') {
            $metaOpenGraph   = !empty($element->metaOpenGraph) ? (bool) $element->metaOpenGraph : false;
            $metaTwitterCard = !empty($element->metaTwitterCard) ? (bool) $element->metaTwitterCard : false;
            $ogImage         = !empty($element->og_image) ? Uri::base() . explode('#', $element->og_image)[0] : null;
            $ogImageAlt      = !empty($element->og_image) ? $ogTitle : null;
            $ogType          = $element->og_type ?? $this->params->get('og_type');
            $ogSitename      = $element->og_sitename ?? $this->params->get('og_sitename') ?? $this->config->get('sitename');
        } else {
            $metaOpenGraph   = !empty($element->get('metaOpenGraph')) ? (bool) $element->get('metaOpenGraph') : false;
            $metaTwitterCard = !empty($element->get('metaTwitterCard')) ? (bool) $element->get('metaTwitterCard') : false;
            $ogImage         = !empty($element->get('og_image')) ? Uri::base() . explode('#', $element->get('og_image'))[0] : null;
            $ogImageAlt      = !empty($element->get('og_image')) ? $ogTitle : null;
            $ogType          = $element->get('og_type') ?? $this->params->get('og_type');
            $ogSitename      = $element->get('og_sitename') ?? $this->params->get('og_sitename') ?? $this->config->get('sitename');
        }

        if ($metaOpenGraph || $metaTwitterCard) {
            $this->$selector = [
                'og:title'       => $ogTitle,
                'og:description' => $ogDescription,
                'og:image'       => $ogImage,
                'og:image:alt'   => $ogImageAlt,
                'og:type'        => $ogType,
                'og:sitename'    => $ogSitename,
                'og:url'         => Uri::getInstance()->toString()
            ];

            if ($metaTwitterCard) {
                $this->$selector['twitter:image:alt'] = null != $ogImage ? $ogImageAlt : null;
                $this->$selector['twitter:site']      = $this->params->get('tw_site_name');
                $this->$selector['twitter:card']      = 'summary_large_image';
            }

            if ($metaOpenGraph && !empty($this->params->get('fb_application_id'))) {
                $this->$selector['fb:app_id'] = $this->params->get('fb_application_id');
            }
        }
    }

    private function setMetaTags(array $batch)
    {
        $head = $this->doc->getHeadData()['custom'];

        if ($this->params->get('removeOtherTags')) {
            $pattern = '/(<meta property=\"og\:|<meta name=\"og\:|<meta name=\"twitter\:).*?\n?.*\/>/';
            $head    = array_filter($head, fn($var) => !preg_match($pattern, $var));
        }

        foreach ($batch as $key => $value) {
            if (null != $value) {
                $head[] = sprintf(
                    '<meta %s="%s" content="%s" />',
                    (str_starts_with($key,'twitter') ? 'name' : 'property'),
                    $key,
                    $value
                );
            }
        }

        if (!empty($head)) {
            $this->doc->setHeadData(['custom' => $head]);
        }
    }
}
