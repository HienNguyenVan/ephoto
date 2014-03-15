<?php

$plugin = OW::getPluginManager()->getPlugin('advancedphoto');
OW::getRouter()->removeRoute('photo_list_index');
OW::getRouter()->addRoute(new OW_Route('photo_list_index', 'photo/', 'ADVANCEDPHOTO_CTRL_Photo', 'viewList'));

OW::getRouter()->addRoute(new OW_Route('photo_list_albums', 'photo/albums', 'ADVANCEDPHOTO_CTRL_Photo', 'albums'));

OW::getRouter()->removeRoute('view_tagged_photo_list_st');
OW::getRouter()->addRoute(new OW_Route('view_tagged_photo_list_st', 'photo/viewlist/tagged/', 'ADVANCEDPHOTO_CTRL_Photo', 'viewTaggedList'));
OW::getRouter()->removeRoute('photo_upload');
OW::getRouter()->addRoute(new OW_Route('photo_upload', 'photo/upload', 'ADVANCEDPHOTO_CTRL_Upload', 'index'));
OW::getRouter()->addRoute(new OW_Route('save_photo', 'photo/save', 'ADVANCEDPHOTO_CTRL_Upload', 'save'));

OW::getRouter()->removeRoute('view_tagged_photo_list');
OW::getRouter()->addRoute(new OW_Route('view_tagged_photo_list', 'photo/viewlist/tagged/:tag', 'ADVANCEDPHOTO_CTRL_Photo', 'viewTaggedList'));

OW::getRouter()->removeRoute('photo_user_albums');
OW::getRouter()->addRoute(new OW_Route('photo_user_albums', 'photo/useralbums/:user/', 'ADVANCEDPHOTO_CTRL_Photo', 'userAlbums'));

OW::getRouter()->removeRoute('photo_user_album');
OW::getRouter()->addRoute(new OW_Route('photo_user_album', 'photo/useralbum/:user/:album', 'ADVANCEDPHOTO_CTRL_Photo', 'userAlbum'));

OW::getRouter()->addRoute(new OW_Route('advancedphoto_admin_config', 'admin/advancedphoto', 'ADVANCEDPHOTO_CTRL_Admin', 'index'));
OW::getRouter()->addRoute(new OW_Route('advancedphoto_categories', 'admin/advancedphoto/categories', "ADVANCEDPHOTO_CTRL_Admin", 'categories'));

OW::getRouter()->addRoute(new OW_Route('advancedphoto_uninstall', 'admin/advancedphoto/uninstall', 'ADVANCEDPHOTO_CTRL_Admin', 'uninstall'));
//OW::getThemeManager()->addDecorator('photo_items', $plugin->getKey());
//OW::getThemeManager()->addDecorator('photo_list_item', $plugin->getKey());

function onFialize($event){
	OW::getDocument()->addStyleSheet(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticCssUrl() . 'styles.css');
	OW::getDocument()->addStyleSheet(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticCssUrl() . 'font-awesome.css');
}
OW::getEventManager()->bind(OW_EventManager::ON_FINALIZE, 'onFialize');
