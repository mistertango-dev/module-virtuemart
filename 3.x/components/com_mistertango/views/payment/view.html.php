<?php

defined('_JEXEC') or die;

/**
 * Class MistertangoViewpayment
 */
class MistertangoViewpayment extends JViewLegacy
{
	/**
	 * @param null $tpl
	 */
	public function display($tpl = null)
	{
		$app     = JFactory::getApplication();
		$uri     = JUri::getInstance();
		$error   = null;
		$rows    = null;
		$results = null;
		$total   = 0;

		$params = $app->getParams();

		$menus = $app->getMenu();
		$menu  = $menus->getActive();

		if (is_object($menu))
		{
			$menu_params = new JRegistry;
			$menu_params->loadString($menu->params);

			if (!$menu_params->get('page_title'))
			{
				$params->set('page_title', JText::_('Payment'));
			}
		}
		else
		{
			$params->set('page_title', JText::_('Payment'));
		}

		$title = $params->get('page_title');

		if ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);

		if ($params->get('menu-meta_description'))
		{
			$this->document->setDescription($params->get('menu-meta_description'));
		}

		if ($params->get('menu-meta_keywords'))
		{
			$this->document->setMetadata('keywords', $params->get('menu-meta_keywords'));
		}

		if ($params->get('robots'))
		{
			$this->document->setMetadata('robots', $params->get('robots'));
		}

		parent::display($tpl);
	}
}
