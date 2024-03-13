<?php
/**
 * @version 1.0
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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_content/src/Model', 'ArticlesModel');

final class OpenGraph extends CMSPlugin
{
	protected $app;
	private $metaItemOg 	= [];
	private $metaMenuOg		= [];
	private $metaPluginOg 	= [];

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
		$app 		= Factory::getApplication();
		$doc 		= Factory::getDocument();
		$input		= $app->input;
		$id 		= $input->get('id');
		$fbAppId 	= $this->params->get('fb_application_id');
		$twSiteName = $this->params->get('tw_site_name');

		if ($app->isClient('site')) {
			$model = $app->bootComponent($input->get('option'))->getMVCFactory()->createModel('Article', 'Site');
			$menu  = $app->getMenu()->getActive()->getParams();

			$this->getOg('item', $model, $id);
			$this->getOg('menu', $menu);
			$this->getOg('plugin');

			switch (true) {
				case (!empty($this->metaMenuOg) && !empty($this->metaItemOg)) || (!empty($this->metaMenuOg) && empty($this->metaItemOg)):
					$this->setMetaTags($this->metaMenuOg);
				break;
				case empty($this->metaMenuOg) && !empty($this->metaItemOg):
					$this->setMetaTags($this->metaItemOg);
				break;
				case empty($this->metaMenuOg) && empty($this->metaItemOg):
					$this->setMetaTags($this->metaPluginOg);
				break;
			}
		}
	}

	private function getOg(string $method, $element = null, int $id = null)
	{
		switch ($method) {
			case 'item':
				if (false == $element || null == $id || $id == 0) {
					return;
				}
				$element = $element->getItem($id)->params;
				$selector = 'metaItemOg';
			break;
			case 'menu':
				$selector = 'metaMenuOg';
			break;
			case 'plugin':
				$element = $this->params;
				$selector = 'metaPluginOg';
			break;
		}

		if (
			($element->get('metaOpenGraph') || $element->get('metaTwitterCard'))
			&& (
				null != $element->get('og_title')
				|| null != $element->get('og_description')
				|| null != $element->get('og_image')
				|| null != $element->get('og_sitename')
			)
		) {
			$this->$selector = [
				'og:title' 		 => $element->get('og_title'),
				'og:description' => $element->get('og_description'),
				'og:image' 		 => !empty($element->get('og_image')) ? Uri::base() . $element->get('og_image') : null,
				'og:image:alt'	 => !empty($element->get('og_image')) ? $element->get('og_title') : null,
				'og:type' 		 => $element->get('og_type') ?? $this->params->get('og_type'),
				'og:sitename' 	 => $element->get('og_sitename'),
				'og:url'		 => Uri::getInstance()->toString()
			];

			if ($element->get('metaTwitterCard') && !empty($element->get('og_title')) && !empty($element->get('og_image'))) {
				$this->$selector['twitter:card'] = 'summary_large_image';
				$this->$selector['twitter:image:alt'] = $element->get('og_title');
			}

			if ($element->get('metaOpenGraph') && !empty($this->params->get('fb_application_id'))) {
				$this->$selector['fb:app_id'] = $this->params->get('fb_application_id');
			}

			if ($element->get('metaTwitterCard') && !empty($this->params->get('tw_site_name'))) {
				$this->$selector['twitter:site'] = $this->params->get('tw_site_name');
			}
		}
	}

	private function setMetaTags(array $batch) {
		$ogMeta = [];

		foreach ($batch as $key => $value) {
			if (null != $value) {
				$ogMeta[] = '<meta ' . (str_starts_with($key,'twitter') ? 'name' : 'property') . '="' . $key . '" content="' . $value . '" />';
			}
		}

		if (!empty($ogMeta)) {
			Factory::getDocument()->setHeadData(['custom' => $ogMeta]);
		}
	}
}
