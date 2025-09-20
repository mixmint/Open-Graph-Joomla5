<?php
/**
* @version 1.3
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

                $pluginMetaOG     = (bool) $this->params->get('metaOpenGraph', 0);
                $pluginMetaTC     = (bool) $this->params->get('metaTwitterCard', 0);
                $useArticleConfig = (bool) $this->params->get('useArticleContent', 0);

                $articleMetaOG = !empty($element->metaOpenGraph);
                $articleMetaTC = !empty($element->metaTwitterCard);

                $metaOpenGraph   = $articleMetaOG ? true : ($useArticleConfig && $pluginMetaOG);
                $metaTwitterCard = $articleMetaTC ? true : ($useArticleConfig && $pluginMetaTC);

                if ($articleMetaOG || $articleMetaTC) {
                    $ogTitle = !empty($element->og_title)
                        ? $this->sanitizeOgValue($element->og_title)
                        : $this->sanitizeOgValue($article->title);
                } elseif ($useArticleConfig && ($pluginMetaOG || $pluginMetaTC)) {
                    $ogTitle = $this->sanitizeOgValue($article->title);
                } else {
                    $ogTitle = $this->sanitizeOgValue(
                        !empty($element->og_title)
                            ? $element->og_title
                            : ($this->params->get('og_title') ?: $article->title)
                    );
                }

                if ($articleMetaOG || $articleMetaTC) {
                    $ogDescription = !empty($element->og_description)
                        ? $this->sanitizeOgValue($element->og_description)
                        : $this->sanitizeOgValue(!empty($article->metadesc) ? $article->metadesc : $introtext);
                } elseif ($useArticleConfig && ($pluginMetaOG || $pluginMetaTC)) {
                    $ogDescription = $this->sanitizeOgValue(!empty($article->metadesc) ? $article->metadesc : $introtext);
                } else {
                    $ogDescription = $this->sanitizeOgValue(
                        !empty($element->og_description)
                            ? $element->og_description
                            : ($this->params->get('og_description') ?: (!empty($article->metadesc) ? $article->metadesc : $introtext))
                    );
                }

                $ogImage = null;

                if ($articleMetaOG || $articleMetaTC) {
                    if (!empty($element->og_image)) {
                        $ogImage = $this->resolveImagePath(explode('#', $element->og_image)[0]);
                    } else {
                        $ogImage = $this->resolveArticleImage($article);
                    }
                } else {
                    if ($useArticleConfig && ($pluginMetaOG || $pluginMetaTC)) {
                        $ogImage = $this->resolveArticleImage($article);
                    } else {
                        $ogImage = $this->resolveImagePath(!empty($element->og_image) ? explode('#', $element->og_image)[0] : null)
                            ?? $this->resolveImagePath($this->params->get('og_image'));
                    }
                }

                $ogImageAlt = !empty($ogImage) ? $this->sanitizeOgValue($ogTitle) : null;

                $ogType     = $element->og_type ?? $this->params->get('og_type');
                $ogSitename = $element->og_sitename ?? $this->params->get('og_sitename') ?? $this->config->get('sitename');

                break;

            case 'menu':
                $selector      = 'metaMenuOg';
                $menuItem      = $element;
                $element       = $element->getParams();

                $ogTitle       = $this->sanitizeOgValue(
                    !empty($element->get('og_title')) ? $element->get('og_title') : $menuItem->title
                );

                $ogDescription = $this->sanitizeOgValue(
                    $element->get('og_description')
                    ?? (!empty($element->get('menu-meta_description')) ? $element->get('menu-meta_description') : null)
                    ?? $this->params->get('og_description')
                );

                $metaOpenGraph   = !empty($element->get('metaOpenGraph')) ? (bool) $element->get('metaOpenGraph') : false;
                $metaTwitterCard = !empty($element->get('metaTwitterCard')) ? (bool) $element->get('metaTwitterCard') : false;
                $ogImage         = !empty($element->get('og_image')) ? $this->resolveImagePath($element->get('og_image')) : null;
                $ogImageAlt      = !empty($ogImage) ? $ogTitle : null;
                $ogType          = $element->get('og_type') ?? $this->params->get('og_type');
                $ogSitename      = $element->get('og_sitename') ?? $this->params->get('og_sitename') ?? $this->config->get('sitename');

                break;

            case 'plugin':
                $selector      = 'metaPluginOg';
                $element       = $this->params;

                $ogTitle       = $this->sanitizeOgValue(
                    !empty($element->get('og_title')) ? $element->get('og_title') : $this->config->get('sitename')
                );

                $ogDescription = $this->sanitizeOgValue(
                    !empty($element->get('og_description')) ? $element->get('og_description') : $this->config->get('MetaDesc')
                );

                $metaOpenGraph   = !empty($element->get('metaOpenGraph')) ? (bool) $element->get('metaOpenGraph') : false;
                $metaTwitterCard = !empty($element->get('metaTwitterCard')) ? (bool) $element->get('metaTwitterCard') : false;
                $ogImage         = !empty($element->get('og_image')) ? $this->resolveImagePath($element->get('og_image')) : null;
                $ogImageAlt      = !empty($ogImage) ? $ogTitle : null;
                $ogType          = $element->get('og_type') ?? $this->params->get('og_type');
                $ogSitename      = $element->get('og_sitename') ?? $this->params->get('og_sitename') ?? $this->config->get('sitename');

                break;
        }

        if (!empty($metaOpenGraph) || !empty($metaTwitterCard)) {
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

    private function sanitizeOgValue(?string $value): ?string
    {
        return !empty($value)
            ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : null;
    }

    /**
    * Resolve a raw image reference (local path OR full http(s) URL) to a usable URL,
    * rejecting remote URLs that would cause mixed-content (http vs https).
    * Returns null if the image is not acceptable or not found.
    */
    private function resolveImagePath(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = explode('#', $raw)[0];

        $siteScheme = parse_url(Uri::base(), PHP_URL_SCHEME) ?: 'http';

        if (preg_match('#^https?://#i', $raw)) {
            $imgScheme = parse_url($raw, PHP_URL_SCHEME) ?: 'http';

            if (strcasecmp($imgScheme, $siteScheme) !== 0) {
                return null;
            }

            return $raw;
        }

        $filePath = defined('JPATH_ROOT') ? JPATH_ROOT . '/' . ltrim($raw, '/') : __DIR__ . '/' . ltrim($raw, '/');

        if (file_exists($filePath) && is_file($filePath)) {
            return Uri::base() . ltrim($raw, '/');
        }

        return null;
    }

    /**
    * Choose the article image (image_intro / image_fulltext) based on which appears first
    * in the rendered article content. Uses resolveImagePath() to validate each candidate.
    */
    private function resolveArticleImage(object $article): ?string
    {
        $images = json_decode($article->images);
        $candidates = [];

        if (!empty($images->image_intro)) {
            $candidates['image_intro'] = $images->image_intro;
        }
        if (!empty($images->image_fulltext)) {
            $candidates['image_fulltext'] = $images->image_fulltext;
        }

        if (empty($candidates)) {
            return null;
        }

        $rendered = (string) ($article->introtext ?? '') . (string) ($article->fulltext ?? '');

        if (count($candidates) > 1) {
            $positions = [];
            foreach ($candidates as $key => $path) {
                $base = basename($path);
                $positions[$key] = $base && mb_strpos($rendered, $base) !== false ? mb_strpos($rendered, $base) : PHP_INT_MAX;
            }
            asort($positions, SORT_NUMERIC);
            $ordered = array_keys($positions);
        } else {
            $ordered = array_keys($candidates);
        }

        foreach ($ordered as $key) {
            $resolved = $this->resolveImagePath($candidates[$key]);
            if (!empty($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    private function setMetaTags(array $batch)
    {
        $head = $this->doc->getHeadData()['custom'];

        if ($this->params->get('removeOtherTags')) {
            $pattern = '/<meta\s+(?:property|name)="(?:og:|twitter:)[^"]*".*?>/i';
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
