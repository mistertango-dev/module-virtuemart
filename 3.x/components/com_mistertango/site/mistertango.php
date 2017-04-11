<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_search
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
$language = JFactory::getLanguage();
$language->load('com_mistertango', JPATH_SITE, $language->getTag(), true);

$controller = JControllerLegacy::getInstance('Mistertango');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
