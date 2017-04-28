<?php

defined('_JEXEC') or die;
$language = JFactory::getLanguage();
$language->load('com_mistertango', JPATH_SITE, $language->getTag(), true);

$controller = JControllerLegacy::getInstance('Mistertango');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
