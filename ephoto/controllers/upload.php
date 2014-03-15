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
class PHOTO_CTRL_Upload extends OW_ActionController
{
    /**
     * @var PHOTO_BOL_PhotoService
     */
    private $photoService;
    /**
     * @var PHOTO_BOL_PhotoAlbumService
     */
    private $photoAlbumService;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->photoService = PHOTO_BOL_PhotoService::getInstance();
        $this->photoAlbumService = PHOTO_BOL_PhotoAlbumService::getInstance();

        if ( !OW::getRequest()->isAjax() )
        {
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'photo', 'photo');
        }
    }

    public function flashUpload( )
    {
        $photo = $_FILES['photo'];
        $order = !empty($_POST['order']) ? (int) $_POST['order'] : 0;
        $tmpPhotoService = PHOTO_BOL_PhotoTemporaryService::getInstance();

        $config = OW::getConfig();
        $accepted = floatval($config->getValue('photo', 'accepted_filesize') * 1024 * 1024);

        if ( strlen($photo['tmp_name']) )
        {
            if ( !UTIL_File::validateImage($photo['name']) || $photo['size'] > $accepted )
            {
                echo "error"; exit;
            }

            if ( $tmpPhotoService->addTemporaryPhoto($photo['tmp_name'], OW::getUser()->getId(), $order) )
            {
                echo "ok"; exit;
            }
        }
        
        echo "error"; exit;
    }
    
    function delete_dir($src) { 
        $dir = opendir($src);
        while(false !== ( $file = readdir($dir)) ) { 
            if (( $file != '.' ) && ( $file != '..' )) { 
                if ( is_dir($src . '/' . $file) ) { 
                    $this->delete_dir($src . '/' . $file); 
                } 
                else { 
                    unlink($src . '/' . $file); 
                } 
            } 
        } 
        rmdir($src);
        closedir($dir); 
    }
    
    /**
     * Default action
     */
    public function index( array $params = null )
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

        OW::getDocument()->setHeading($language->text('photo', 'upload_photos'));
        OW::getDocument()->setHeadingIconClass('ow_ic_picture');

        if ( !OW::getUser()->isAuthorized('photo', 'upload') )
        {
            $this->assign('auth_msg', $language->text('photo', 'auth_upload_permissions'));
            return;
        }

        if ( !empty($params['album']) && (int) $params['album'] )
        {
            $albumId = (int) $params['album'];
            $uploadToAlbum = PHOTO_BOL_PhotoAlbumService::getInstance()->findAlbumById($albumId);
            if ( !$uploadToAlbum || $uploadToAlbum->userId != $userId )
            {
                $this->redirect(OW::getRouter()->urlForRoute('photo_upload'));
            }
        }

        $eventParams = array('pluginKey' => 'photo', 'action' => 'add_photo');
        $credits = OW::getEventManager()->call('usercredits.check_balance', $eventParams);
        
        $tmpPhotoService = PHOTO_BOL_PhotoTemporaryService::getInstance();
        
        if ( $credits === false )
        {
            $this->assign('auth_msg', OW::getEventManager()->call('usercredits.error_message', $eventParams));
        }
        else if ( !($this->photoService->countUserPhotos($userId) <= $userQuota) )
        {
            $this->assign('auth_msg', $language->text('photo', 'quota_exceeded', array('limit' => $userQuota)));
        }
        else
        {
            $fileSizeLimit = $config->getValue('photo', 'accepted_filesize');
            $this->assign('limitMsg', $language->text('photo', 'size_limit', array('size' => $fileSizeLimit)));

            $this->assign('auth_msg', null);

            $photoUploadForm = new PhotoUploadForm();
            if ( isset($uploadToAlbum) )
            {
                $photoUploadForm->getElement('albumId')->setValue($uploadToAlbum->id);
            }
            $this->addForm($photoUploadForm);
            
            if ( OW::getRequest()->isPost() )
            {
                if ( !$photoUploadForm->isValid($_POST) )
                {
                    OW::getFeedback()->warning($language->text('photo', 'no_photo_uploaded'));
                    $this->redirect();
                }
                
                $values = $photoUploadForm->getValues();
                $photosArray = $values['photos'];
                if ( !count($photosArray['name']) )
                {
                    OW::getFeedback()->warning($language->text('photo', 'no_photo_uploaded'));
                    $this->redirect();
                }
                $accepted = floatval($config->getValue('photo', 'accepted_filesize') * 1024 * 1024);
        
                // Delete old temporary photos
                $tmpPhotoService->deleteUserTemporaryPhotos($userId);
        
                $uploadedCount = 0;
                $selectedCount = 0;
                $photosArray = array_reverse($photosArray);
                
                $order = 0;
                for ( $i = 0; $i < count($photosArray['name']); $i++ )
                {
                    if ( strlen($photosArray['name'][$i]) )
                    {
                        $selectedCount++;
                    }
                    
                    if ( strlen($photosArray['tmp_name'][$i]) )
                    {
                        $ziptype = ['application/zip', 'application/x-zip', 'application/octet-stream', 'application/x-zip-compressed'];
                        if ( !(UTIL_File::validateImage($photosArray['name'][$i]) || in_array($photosArray['type'][$i], $ziptype)) || $photosArray['size'][$i] > $accepted )
                        {
                            continue;
                        }
                        echo '123';die;
                        if(in_array($photosArray['type'][$i], $ziptype)){
                             $fileName = substr($photosArray['name'][$i], 0 , strlen($photosArray['name'][$i]) - 4);
    
                             $folder = md5(time()); 
                             $tempDir = OW::getPluginManager()->getPlugin('photo')->getUserFilesDir(). $folder. DS; 
                             
                             $tempFile = OW::getPluginManager()->getPlugin('photo')->getUserFilesDir(). uniqid($folder) . '.zip';
                             
                             copy($photosArray['tmp_name'][$i], $tempFile);


                            $zip = new ZipArchive();
            
                            if ( $zip->open($tempFile) === true )
                            {
                                $zip->extractTo($tempDir);
                                $zip->close();
                                
                                $filesTempMovedDir = file_exists($tempDir. $fileName)? $tempDir. $fileName : $tempDir;
                                
                                $imgesFile = array();
                                $imgesFile = array_merge($imgesFile, glob($filesTempMovedDir."\*.jpg"));
                                $imgesFile = array_merge($imgesFile, glob($filesTempMovedDir."\*.png"));
                                $imgesFile = array_merge($imgesFile, glob($filesTempMovedDir."\*.jpge"));

                                //move file
                                 foreach ($imgesFile as $filename) {
                                        $tmpPhoto = new PHOTO_BOL_PhotoTemporary();
                                        $tmpPhoto->userId = $userId;
                                        $tmpPhoto->addDatetime = time();
                                        $tmpPhoto->hasFullsize = 0;
                                        $tmpPhoto->order = $order;
                                        PHOTO_BOL_PhotoTemporaryDao::getInstance()->save($tmpPhoto);
                                        
                                        $preview = PHOTO_BOL_PhotoTemporaryDao::getInstance()->getTemporaryPhotoPath($tmpPhoto->id, 1);
                                        $main = PHOTO_BOL_PhotoTemporaryDao::getInstance()->getTemporaryPhotoPath($tmpPhoto->id, 2);
                                        $original = PHOTO_BOL_PhotoTemporaryDao::getInstance()->getTemporaryPhotoPath($tmpPhoto->id, 3);
                                        
                                        $config = OW::getConfig();
                                        $width = $config->getValue('photo', 'main_image_width');
                                        $height = $config->getValue('photo', 'main_image_height');
                                        $previewWidth = $config->getValue('photo', 'preview_image_width');
                                        $previewHeight = $config->getValue('photo', 'preview_image_height');
                                        
                                        try {
                                            $image = new UTIL_Image($filename, "jpg");
                                            
                                            $mainPhoto = $image
                                                ->resizeImage($width, $height)
                                                ->saveImage($main);
                                
                                            if ( (bool) $config->getValue('photo', 'store_fullsize') && $mainPhoto->imageResized() )
                                            {
                                                $originalImage = new UTIL_Image($source);
                                                $res = (int) $config->getValue('photo', 'fullsize_resolution');
                                                $res = $res ? $res : 1024;
                                                $originalImage
                                                    ->resizeImage($res, $res)
                                                    ->saveImage($original);
                                                
                                                $tmpPhoto->hasFullsize = 1;
                                                PHOTO_BOL_PhotoTemporaryDao::getInstance()->save($tmpPhoto);
                                            }
                                            
                                            $mainPhoto
                                                ->resizeImage($previewWidth, $previewHeight, true)
                                                ->saveImage($preview);
                                        }
                                        catch ( WideImage_Exception $e )
                                        {
                                            PHOTO_BOL_PhotoTemporaryDao::getInstance()->deleteById($tmpPhoto->id);
                                            continue;
                                        }
                                        
                                        $order++;   
                                        $uploadedCount++;   
                                 }

                            }else{
                                OW::getFeedback()->error(OW::getLanguage()->text('photo', 'extract_zip_file_error'));
                                //$this->redirectToAction('index');
                            }
                            
                            //unlink
                            $this->delete_dir($tempDir);
                            unlink($tempFile);
                        }
                        else {
                            if ( $tmpPhotoService->addTemporaryPhoto($photosArray['tmp_name'][$i], $userId, $order) )
                            {
                                $uploadedCount++;
                            }
                        }    
                    }
                    $order ++;
                }
        
                if ( $uploadedCount == 0 )
                {
                    OW::getFeedback()->warning($language->text('photo', 'no_photo_uploaded'));
                    $this->redirect();
                }
                else if ( $selectedCount > $uploadedCount )
                {
                    OW::getFeedback()->warning($language->text('photo', 'not_all_photos_uploaded'));
                }

                if ( !empty($values['albumId']) )
                {
                    $this->redirect(OW::getRouter()->urlForRoute('photo_upload_submit_album', array('album' => $values['albumId'])));
                }
                else
                {
                    $this->redirect(OW::getRouter()->urlForRoute('photo_upload_submit'));
                }
            }
        }

        $albumsUrl = OW::getRouter()->urlForRoute(
            'photo_user_albums',
            array('user' => BOL_UserService::getInstance()->getUserName($userId))
        );

        $js = new UTIL_JsGenerator();
        $js->newVariable('myAlbumsUrl', $albumsUrl);
        $js->jQueryEvent('#button-my-albums', 'click', 'window.location.href=myAlbumsUrl;');

        OW::getDocument()->addOnloadScript($js);
        
        $advancedUpload = OW::getConfig()->getValue('photo', 'advanced_upload_enabled');
        
        if ( $advancedUpload )
        {
            $menuItems = array();
            
            $item = new BASE_MenuItem();
            $item->setLabel($language->text('photo', 'advanced_upload'));
            $item->setUrl('js-call:upload_advanced');
            $item->setKey('upload_advanced');
            $item->setIconClass('ow_ic_files');
            $item->setOrder(1);
            $item->setActive(true);
            array_push($menuItems, $item);
            
            $item = new BASE_MenuItem();
            $item->setLabel($language->text('photo', 'simple_upload'));
            $item->setUrl('js-call:upload_simple');
            $item->setKey('upload_simple');
            $item->setIconClass('ow_ic_file');
            $item->setOrder(2);
            $item->setActive(false);
            array_push($menuItems, $item);
            
            $menu = new BASE_CMP_ContentMenu($menuItems);
            $this->addComponent('menu', $menu);
            
            $menuJs = 'var $tabs = $("a[href^=js-call]", "#ow_photo_upload_menu");
                $tabs.click(function(){
                    var $this = $(this);
                    $tabs.parent().removeClass("active");
                    $this.parent().addClass("active");
                    $(".ow_photo_upload_page").hide();
                    $("#page_" + $this.data("tab_content")).show();
                     
                }).each(function(){
                    var command = this.href.split(":");
                    $(this).data("tab_content", command[1]);
                    $(this).attr("href", "javascript://");
                });';
            
            OW::getDocument()->addOnloadScript($menuJs);

            if ( !empty($uploadToAlbum) )
            {
                $completeUrl = OW::getRouter()->urlForRoute('photo_upload_submit_album', array('album' => $uploadToAlbum->id));
            }
            else
            {
                $completeUrl = OW::getRouter()->urlForRoute('photo_upload_submit');
            }

            OW::getDocument()->addScriptDeclaration(
                'window.flashUploadComplete = function() {
                    document.location.href = '.json_encode($completeUrl).';
                };');
            
            $plugin = OW::getPluginManager()->getPlugin('photo');
            OW::getDocument()->addScript($plugin->getStaticJsUrl() . 'swfobject.js');
            
            $mainSwfUrl = $plugin->getStaticUrl() . 'swf/main.swf';
            $xiSwfUrl = $plugin->getStaticUrl() . 'swf/playerProductInstall.swf';
            
            $res = OW::getConfig()->getValue('photo', 'fullsize_resolution');
            
            $path = OW::getRouter()->urlForRoute('photo.flash_upload');
            preg_match('/^http(s)?:\/\/[^?#%\/]+\/(.*)/', $path, $match);
            $path = $match[2];
            
            $js = 'var swfVersionStr = "10.0.0";
            var xiSwfUrlStr = "'.$xiSwfUrl.'";
            var flashvars = {};
            flashvars.uploadPath = "'.$path.'";
            flashvars.fileName = "photo";
            flashvars.lang = '.$this->getLangXml().';
            flashvars.album = "my-album";
            flashvars.description = "description";
            flashvars.res = '.json_encode($res ? $res : 1024).';
            var params = {};
            params.wmode = "transparent";
            params.quality = "high";
            params.bgcolor = "#ffffff";
            params.allowscriptaccess = "sameDomain";
            params.allowfullscreen = "false";
            var attributes = {};
            attributes.id = "Main";
            attributes.name = "Main";
            attributes.align = "middle";
            swfobject.embedSWF("'.$mainSwfUrl.'", "ow_flash_photo_uploader", "695", "440", swfVersionStr, xiSwfUrlStr, flashvars, params, attributes);
            swfobject.createCSS("#ow_flash_photo_uploader", "display:block; text-align:left;");';
            
            OW::getDocument()->addOnloadScript($js);
            
            $tmpPhotoService->deleteUserTemporaryPhotos($userId);
        }

        $this->assign('advancedUpload', $advancedUpload);
        
        OW::getDocument()->setTitle(OW::getLanguage()->text('photo', 'meta_title_photo_upload'));
    }
    
    private function getLangXml( )
    {
        $lang = OW::getLanguage();
        
        $xml = "<langs>". 
            "<browse>".$lang->text('photo', 'advanced_upload_browse')."</browse>".
            "<upload>".$lang->text('photo', 'advanced_upload_upload')."</upload>". 
            "<processing>".$lang->text('photo', 'advanced_upload_processing')."</processing>".
            "<uploading>".$lang->text('photo', 'advanced_upload_uploading')."</uploading>".
            "<complete>".$lang->text('photo', 'advanced_upload_complete')."</complete>".
            "<popup_add_more>".$lang->text('photo', 'advanced_upload_add_more')."</popup_add_more>".
            "<popup_upload>".$lang->text('photo', 'advanced_upload_yes')."</popup_upload>".
            "<upload_confirm_question>".$lang->text('photo', 'advanced_upload_confirm')."</upload_confirm_question>".
            "</langs>";
        
        return json_encode($xml);
    }
    
    public function submit( array $params = null )
    {
        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new AuthenticateException();
        }

        $lang = OW::getLanguage();
        $service = PHOTO_BOL_PhotoTemporaryService::getInstance();
        $list = $service->findUserTemporaryPhotos(OW::getUser()->getId(), 'order');

        if ( !$list )
        {
            $this->redirectToAction('index');
        }

        $this->assign('list', $list);
        
        $form = new PhotoSubmitForm($list);
        if ( !empty($params['album']) && (int) $params['album'] )
        {
            $album = PHOTO_BOL_PhotoAlbumService::getInstance()->findAlbumById($params['album']);
            if ( $album && $album->userId == OW::getUser()->getId() )
            {
                $form->getElement('album')->setValue($album->name);
            }
        }
        $this->addForm($form);

        $slots = array();
        foreach ( $list as $photo )
        {
            $slots[$photo['dto']->id] = array('id' => $photo['dto']->id, 'tag' => '', 'desc' => '');
        }
        
        $lang->addKeyForJs('photo', 'confirm_delete');
        $lang->addKeyForJs('photo', 'add_tags');
        $lang->addKeyForJs('photo', 'describe_photo');
        $lang->addKeyForJs('photo', 'no_photo_selected');
        $lang->addKeyForJs('photo', 'add_description');
        
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'upload_photo.js');
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl().'jquery.tagsinput.js');
        OW::getDocument()->addOnloadScript("$('#photo-tag-input').tagsInput({'height':'auto', 'width':'auto', 'interactive':true, 'defaultText':'".OW::getLanguage()->text('base', 'tags_input_field_invitation')."', 'removeWithBackspace':true, 'minChars':3, 'maxChars':0, 'placeholderColor':'#666666'});");
        $params = array();
        $params['slots'] = $slots;
        $params['ajaxSubmitResponder'] = OW::getRouter()->urlForRoute('photo.ajax_submit');
        $params['ajaxDeleteResponder'] = OW::getRouter()->urlForRoute('photo.ajax_delete');
        $params['formId'] = $form->getId();
        $params['singleSlotId'] = count($list) == 1 ? $photo['dto']->id : 0;
        OW::getDocument()->addOnloadScript("var upload_photo = new UploadPhoto(".json_encode($params).");");
        
        OW::getDocument()->setHeading($lang->text('photo', 'describe_photos'));
        OW::getDocument()->setHeadingIconClass('ow_ic_picture');
        OW::getDocument()->setTitle($lang->text('photo', 'meta_title_photo_upload'));
    }

    /**
     * Prepare values for suggest field
     *
     * @param array $params
     */
    public function suggestAlbum( array $params )
    {
        $userId = trim($params['userId']);

        if ( OW::getRequest()->isAjax() )
        {
            $albums = $this->photoAlbumService->suggestUserAlbums($userId, $_GET['q']);

            if ( $albums )
            {
                foreach ( $albums as $album )
                {
                    echo "$album->name\t$album->id\n";
                }
            }
            exit();
        }
        else
        {
            throw new Redirect404Exception();
            exit();
        }
    }
    
    public function ajaxSubmitPhotos()
    {
        $lang = OW::getLanguage();
        
        if ( !strlen($albumName = htmlspecialchars(trim($_POST['album']))) )
        {
            $resp = array('result' => false, 'msg' => $lang->text('photo', 'photo_upload_error'));
            exit(json_encode($resp));
        }
        
        $photoService = PHOTO_BOL_PhotoService::getInstance();
        $tagService = BOL_TagService::getInstance();
        $photoAlbumService = PHOTO_BOL_PhotoAlbumService::getInstance();
        $photoTmpService = PHOTO_BOL_PhotoTemporaryService::getInstance();
        
        $userId = OW::getUser()->getId();
        
        $tmpList = $photoTmpService->findUserTemporaryPhotos($userId, 'order');
        if ( !$tmpList )
        {
            $resp = array('result' => false, 'msg' => $lang->text('photo', 'photo_upload_error'));
            exit(json_encode($resp));
        }

        // check album exists
        if ( !($album = $photoAlbumService->findAlbumByName($albumName, $userId)) )
        {
            $album = new PHOTO_BOL_PhotoAlbum();
            $album->name = $albumName;
            $album->userId = $userId;
            $album->createDatetime = time();

            $photoAlbumService->addAlbum($album);
            $newAlbum = true;
        }
        
        $movedCount = 0;
        $movedArray = array();
        $photos = array();
        
        $slots = $_POST['slots'];
        $tmpList = array_reverse($tmpList);
        
        foreach ( $tmpList as $tmpPhoto )
        {
            $tmpId = $tmpPhoto['dto']->id;
            if ( !empty($slots[$tmpId]) )
            {
                $eventParams = array('pluginKey' => 'photo', 'action' => 'add_photo');
                $credits = OW::getEventManager()->call('usercredits.check_balance', $eventParams);
                if ( $credits === false )
                {
                    $resp = array('result' => false, 'msg' => OW::getEventManager()->call('usercredits.error_message', $eventParams));
                    exit(json_encode($resp));
                }
                
                $photo = $photoTmpService->moveTemporaryPhoto($tmpId, $album->id, $slots[$tmpId]['desc'], $slots[$tmpId]['tag']);
                
                if ( $photo )
                {
                    $photos[] = $photo;
                    $movedArray[] = array('addTimestamp' => time(), 'photoId' => $photo->id);
                    $movedCount++;
                    
                    if ( $credits === true )
                    {
                        OW::getEventManager()->call('usercredits.track_action', $eventParams);
                    }
                }
            }
        }
        
        if ( !empty($movedArray) )
        {
            $event = new OW_Event('plugin.photos.add_photo', $movedArray);
            OW::getEventManager()->trigger($event);
        }
        
        $albumUrl = OW::getRouter()->urlForRoute('photo_user_album', array(
            'user' => BOL_UserService::getInstance()->getUserName($userId),
            'album' => $album->id
        ));

        if ( $movedCount )
        {
            if ( $movedCount == 1 )
            {
                //Newsfeed
                $event = new OW_Event('feed.action', array(
                    'pluginKey' => 'photo',
                    'entityType' => 'photo_comments',
                    'entityId' => $photos[0]->id,
                    'userId' => $userId
                ));
                OW::getEventManager()->trigger($event);
            }
            else
            {
                $content = '';
                $counter = 0;
                $photos = array_reverse($photos);
                
                foreach ( $photos as $photo )
                {
                    if ( $counter == 5 )
                    {
                        $content .= '<span class="ow_remark" style="float: left; display: inline-block; padding-top: 65px"><a class="photo_view_more" href="'.$albumUrl.'"> '
                            . $lang->text('photo', 'feed_more_items', array('moreCount' => $movedCount-5)) . "</a></span>";
                        break;
                    }
                    $id = $photo->id;
                    $pageUrl = $url = OW::getRouter()->urlForRoute('view_photo', array('id' => $id));
                    $content .= '<a style="float: left; margin: 0px 4px 4px 0px;" href="' . $pageUrl . '"><img src="' . $photoService->getPhotoUrl($id, true) . '" height="80" /></a> ';
                    $counter++;
                }
                                
                $content = '<div class="clearfix">'.$content.'</div>';

                $description = $photos[0]->description;
                $diff = false;
                if ( !mb_strlen($description) )
                {
                    $diff = true;
                }
                else 
                {
                    foreach ( $photos as $photo )
                    {
                        if ( $photo->description != $description )
                        {
                            $diff = true;
                            break;
                        }
                    }
                }
                
                //Newsfeed
                $albumName = UTIL_String::truncate(strip_tags($album->name), 25, '...');
                
                if ( $diff )
                {
                    $title = $lang->text('photo', 'feed_multiple_descriptions', 
                        array('number' => $movedCount, 'albumUrl' => $albumUrl, 'albumName' => $albumName)
                    );    
                }
                else 
                {
                    $title = UTIL_String::truncate(strip_tags($description), 100, '...');
                }
                
                $event = new OW_Event('feed.action', array(
                    'pluginKey' => 'photo',
                    'entityType' => 'multiple_photo_upload',
                    'entityId' => $photos[0]->id,
                    'userId' => $userId
                    ), array(
                    'string' => $title,
                    'features' => array('likes'),
                    'content' => $content,
                    'view' => array('iconClass' => 'ow_ic_picture')
                ));
                OW::getEventManager()->trigger($event);
            }
            
            $resp = array('url' => $albumUrl);
            OW::getFeedback()->info($lang->text('photo', 'photos_uploaded', array('count' => $movedCount)));
        }
        else 
        {
            OW::getFeedback()->warning($lang->text('photo', 'no_photo_uploaded'));
            $resp = array('url' => OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'index'));
        }
        
        exit(json_encode($resp));
    }
    
    public function ajaxDeletePhoto()
    {
        if ( empty($_POST['photoId']) || !$_POST['photoId'] )
        {
            $resp = array('result' => false);
            exit(json_encode($resp));
        }
        
        $service = PHOTO_BOL_PhotoTemporaryService::getInstance();
        
        if ( $service->deleteTemporaryPhoto($_POST['photoId']) )
        {
            $resp = array('result' => true);
            exit(json_encode($resp));
        }
    }
}


/**
 * Photo upload form class
 */
class PhotoUploadForm extends Form
{
    public function __construct()
    {
        parent::__construct('photoUploadForm');

        $language = OW::getLanguage();

        $this->setEnctype('multipart/form-data');

        $filesNumber = 5;
        $labels = array();
        for ( $i = 0; $i < $filesNumber; $i++ )
        {
            $labels[$i] = $language->text('photo', 'pic_number', array('number' => $i + 1));
        }

        $filesField = new MultiFileField('photos', $filesNumber, $labels);
        $this->addElement($filesField);
        $filesField->setRequired(true);

        $albumIdField = new HiddenField('albumId');
        $this->addElement($albumIdField);

        $submit = new Submit('upload');
        $submit->setValue($language->text('photo', 'btn_upload'));
        $this->addElement($submit);
    }
}

/**
 * Photo submit form class
 */
class PhotoSubmitForm extends Form
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
