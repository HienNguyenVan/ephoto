<?php
$config = OW::getConfig();
if ( !$config->configExists('ephoto', 'photofeature_per_page') )
{
    $config->addConfig('ephoto', 'photofeature_per_page', 5, 'Featured Photos Count');
}

if ( !$config->configExists('ephoto', 'uninstall_inprogress') )
{
    $config->addConfig('ephoto', 'uninstall_inprogress', 0, 'Plugin is being uninstalled');
}

if ( !$config->configExists('ephoto', 'uninstall_cron_busy') )
{
    $config->addConfig('ephoto', 'uninstall_cron_busy', 0, 'Uninstall queue is busy');
}

if ( !$config->configExists('ephoto', 'maintenance_mode_state') )
{
    $state = (int) $config->getValue('base', 'maintenance');
    $config->addConfig('ephoto', 'maintenance_mode_state', $state, 'Stores site maintenance mode config before plugin uninstallation');
}

OW::getPluginManager()->addPluginSettingsRouteName('ephoto', 'ephoto_admin_config');

OW::getPluginManager()->addUninstallRouteName('ephoto', 'ephoto_uninstall');

$sql = "CREATE TABLE IF NOT EXISTS `" . OW_DB_PREFIX . "photo_categories` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(128) NOT NULL,
  `description` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

INSERT INTO `" . OW_DB_PREFIX . "photo_categories` (`id`, `name`, `description`) VALUES
(1, 'Business', NULL),
(2, 'Arts & Culture', NULL),
(3, 'Entertainment', NULL),
(4, 'Family & Home', NULL),
(5, 'Health', NULL),
(6, 'Recreation', NULL),
(7, 'Personal', NULL),
(8, 'Shopping', NULL),
(9, 'Society', NULL),
(10, 'Sports', NULL),
(11, 'Technology', NULL),
(12, 'Other', NULL);
";
OW::getDbo()->query($sql);

$sql = " ALTER TABLE `" . OW_DB_PREFIX . "photo_album` ADD category_id int(11) default 0; ";
OW::getDbo()->query($sql);

$path = OW::getPluginManager()->getPlugin('ephoto')->getRootDir() . 'langs.zip';
OW::getLanguage()->importPluginLangs($path, 'ephoto');