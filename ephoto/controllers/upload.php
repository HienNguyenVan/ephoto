<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Photo upload action controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.plugin.photo.controllers
 * @since 1.0
 */
class EPHOTO_CTRL_Upload extends OW_ActionController
{
    /**
     * @var PHOTO_BOL_PhotoService
     */
    protected $photoService;
    /**
     * @var PHOTO_BOL_PhotoAlbumService
     */
    protected $photoAlbumService;

    
    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->photoService = PHOTO_BOL_PhotoService::getInstance();
        $this->photoAlbumService = PHOTO_BOL_PhotoAlbumService::getInstance();
    }
    
    public function init()
    {
        if ( !OW::getRequest()->isAjax() )
        {
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'photo', 'photo');
        }
    }

    protected function checkUploadPermissins( $entityType, $entityId )
    {
        // disallow not authenticated access
        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new AuthenticateException();
        }

        $language = OW::getLanguage();
        $userId = OW::getUser()->getId();

        $config = OW::getConfig();
        $userQuota = (int) $config->getValue('photo', 'user_quota');
        
        if ( !OW::getUser()->isAuthorized('photo', 'upload') )
        {
            throw new PHOTO_Exception($language->text('photo', 'auth_upload_permissions'));
        }
        
        $eventParams = array('pluginKey' => 'photo', 'action' => 'add_photo');
        
        $credits = OW::getEventManager()->call('usercredits.check_balance', $eventParams);

        if ( $credits === false )
        {
            throw new PHOTO_Exception(OW::getEventManager()->call('usercredits.error_message', $eventParams));
        }
        else if ( !($this->photoService->countUserPhotos($userId) <= $userQuota) )
        {
            throw new PHOTO_Exception($language->text('photo', 'quota_exceeded', array(
                'limit' => $userQuota
            )));
        }
    }
    
    protected function getEntity( $params )
    {
        if ( empty($params["entityType"]) || empty($params["entityId"]) )
        {
            $params["entityType"] = "user";
            $params["entityId"] = OW::getUser()->getId();
        }
        
        return array($params["entityType"], $params["entityId"]);
    }    
    
    /**
     * 
     * @return BASE_CMP_ContentMenu
     */
    protected function getMenu()
    {
        $advancedUpload = OW::getConfig()->getValue('photo', 'advanced_upload_enabled');
        
        if ( !$advancedUpload )
        {
            return null;
        }
        
        $language = OW::getLanguage();
        
        $menuItems = array();

        $item = new BASE_MenuItem();
        $item->setLabel($language->text('photo', 'simple_upload'));
        $item->setUrl('js-call:upload_simple');
        $item->setKey('upload_simple');
        $item->setIconClass('ow_ic_file');
        $item->setOrder(2);
        $item->setActive(true);
        array_push($menuItems, $item);

        $menu = new BASE_CMP_ContentMenu($menuItems);
        
        return $menu;
    }
    
    /**
     * Default action
     */
    public function index( array $params = null )
    {
        $this->setTemplate(OW::getPluginManager()->getPlugin("ephoto")->getCtrlViewDir() . "upload_index.html");
        
        list($entityType, $entityId) = $this->getEntity($params);
        
        try
        {
            $this->checkUploadPermissins($entityType, $entityId);
        }
        catch ( PHOTO_Exception $e )
        {
            $this->assign("auth_msg", $e->getMessage());
            
            return;
        }
        
        $language = OW::getLanguage();
        $userId = OW::getUser()->getId();

        $config = OW::getConfig();
        
        if ( !empty($params['album']) && (int) $params['album'] )
        {
            $albumId = (int) $params['album'];
            $uploadToAlbum = PHOTO_BOL_PhotoAlbumService::getInstance()->findAlbumById($albumId);
            if ( !$uploadToAlbum || $uploadToAlbum->userId != $userId )
            {
                $this->onUploadReset($entityType, $entityId);
            }
        }
        
        $fileSizeLimit = $config->getValue('photo', 'accepted_filesize');
        $this->assign('limitMsg', $language->text('photo', 'size_limit', array('size' => $fileSizeLimit)));

        $this->assign('auth_msg', null);
        
        $tmpPhotoService = PHOTO_BOL_PhotoTemporaryService::getInstance();
        
        $albumsUrl = OW::getRouter()->urlForRoute(
            'photo_user_albums',
            array('user' => BOL_UserService::getInstance()->getUserName($userId))
        );

        $this->assign("allAlbumsBtn", array(
            "label" => $language->text("photo", "my_albums"),
            "url" => $albumsUrl
        ));

        $menu = $this->getMenu();
        
        if ( $menu !== null )
        {
            $this->addComponent("menu", $menu);
        }

        $service = EPHOTO_BOL_CategoryService::getInstance();

        $agument = array();
        $categories = $service->getCategoriesList();
        foreach ( $categories as $category )
        {
            /* @var $contact CONTACTUS_BOL_Department */
            $agument[$category->id]['name'] = $category->name;
            $agument[$category->id]['id'] = $category->id;
        }

        $this->assign('categories', $agument);

        $this->assign('actionUrl', OW_URL_HOME.'photo/submit');
    }

    public function submit() {
        /**
         * Photo save file to server
         */
        $photos = (array)$_POST['photos'];
        foreach ($photos as $name => $img) {
            $time = time();
            $name = $time . '.' . $name;
            $img = str_replace('data:image/png;base64,', '', $img);
            $img = str_replace(' ', '+', $img);
            $data = base64_decode($img);
            $file = 'ow_userfiles/plugins/ephoto/' . $name;
            $success = file_put_contents($file, $data);
        }
        if ($_POST['type'] == 2) {
            
        }
    }
}

/**
 * Photo submit form class
 */
class PhotoUploadForm extends Form
{
    public function __construct( $list )
    {
        parent::__construct('photoSubmitForm');

        $language = OW::getLanguage();

        // album suggest Field
        $albumField = new SuggestField('album');
        $albumField->setRequired(true);
        $albumField->setMinChars(1);

        // description Field
        $descField = new Textarea('description');
        $this->addElement($descField->setLabel($language->text('photo', 'description')));

        if ( count($list) == 1 )
        {
            $tagsField = new TagsInputField('tags');
            $this->addElement($tagsField->setLabel($language->text('photo', 'tags')));
        }
        
        $userId = OW::getUser()->getId();
        $responderUrl = OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'suggestAlbum', array('userId' => $userId));
        $albumField->setResponderUrl($responderUrl);
        $albumField->setLabel($language->text('photo', 'album'));
        $this->addElement($albumField);
        
        $submit = new Submit('submit');
        $this->addElement($submit);
    }
}