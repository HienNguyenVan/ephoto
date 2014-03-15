<?php
OW::getRouter()->removeRoute('photo_list_index');
OW::getRouter()->addRoute(new OW_Route('photo_list_index', 'photo/', 'PHOTO_CTRL_Photo', 'viewList'));

OW::getRouter()->removeRoute('view_tagged_photo_list_st');
OW::getRouter()->addRoute(new OW_Route('view_tagged_photo_list_st', 'photo/viewlist/tagged/', 'PHOTO_CTRL_Photo', 'viewTaggedList'));

OW::getRouter()->removeRoute('view_tagged_photo_list');
OW::getRouter()->addRoute(new OW_Route('view_tagged_photo_list', 'photo/viewlist/tagged/:tag', 'PHOTO_CTRL_Photo', 'viewTaggedList'));

OW::getRouter()->removeRoute('photo_user_albums');
OW::getRouter()->addRoute(new OW_Route('photo_user_albums', 'photo/useralbums/:user/', 'PHOTO_CTRL_Photo', 'userAlbums'));

OW::getRouter()->removeRoute('photo_user_album');
OW::getRouter()->addRoute(new OW_Route('photo_user_album', 'photo/useralbum/:user/:album', 'PHOTO_CTRL_Photo', 'userAlbum'));
