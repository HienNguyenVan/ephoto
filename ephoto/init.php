<?php

$plugin = OW::getPluginManager()->getPlugin('ephoto');
OW::getRouter()->addRoute(new OW_Route('ephoto_list_index', 'ephoto/', 'EPHOTO_CTRL_Photo', 'viewList'));

OW::getRouter()->addRoute(new OW_Route('ephoto_list_albums', 'ephoto/albums', 'EPHOTO_CTRL_Photo', 'albums'));

OW::getRouter()->addRoute(new OW_Route('view_tagged_ephoto_list_st', 'ephoto/viewlist/tagged/', 'EPHOTO_CTRL_Photo', 'viewTaggedList'));
OW::getRouter()->addRoute(new OW_Route('ephoto_upload', 'ephoto/upload', 'EPHOTO_CTRL_Upload', 'index'));
OW::getRouter()->addRoute(new OW_Route('submit_ephoto', 'ephoto/save', 'EPHOTO_CTRL_Upload', 'submit'));

OW::getRouter()->addRoute(new OW_Route('view_tagged_ephoto_list', 'ephoto/viewlist/tagged/:tag', 'EPHOTO_CTRL_Photo', 'viewTaggedList'));

OW::getRouter()->addRoute(new OW_Route('ephoto_user_albums', 'ephoto/useralbums/:user/', 'EPHOTO_CTRL_Photo', 'userAlbums'));

OW::getRouter()->addRoute(new OW_Route('ephoto_user_album', 'ephoto/useralbum/:user/:album', 'EPHOTO_CTRL_Photo', 'userAlbum'));

OW::getRouter()->addRoute(new OW_Route('ephoto_admin_config', 'admin/ephoto', 'EPHOTO_CTRL_Admin', 'index'));
OW::getRouter()->addRoute(new OW_Route('ephoto_categories', 'admin/ephoto/categories', "EPHOTO_CTRL_Admin", 'categories'));

OW::getRouter()->addRoute(new OW_Route('ephoto_uninstall', 'admin/ephoto/uninstall', 'EPHOTO_CTRL_Admin', 'uninstall'));
//OW::getThemeManager()->addDecorator('photo_items', $plugin->getKey());
//OW::getThemeManager()->addDecorator('photo_list_item', $plugin->getKey());

function onFialize($event){
	OW::getDocument()->addStyleSheet(OW::getPluginManager()->getPlugin('ephoto')->getStaticCssUrl() . 'styles.css');
	OW::getDocument()->addStyleSheet(OW::getPluginManager()->getPlugin('ephoto')->getStaticCssUrl() . 'font-awesome.css');
	OW::getDocument()->addScript('/ow_static/plugins/ephoto/js/lib/zip.js');
	OW::getDocument()->addScript('/ow_static/plugins/ephoto/js/main.js');
}
OW::getEventManager()->bind(OW_EventManager::ON_FINALIZE, 'onFialize');
