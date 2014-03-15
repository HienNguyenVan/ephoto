<?php

class ADVANCEDPHOTO_CTRL_Photo extends OW_ActionController
{
	//photo
	public static $isNext = false;
	public static $item_count = 0;
	//album
	public static $isNextAlbum = false;
	public static $item_count_album = 0;
	/**
    * @var BASE_CMP_ContentMenu
    */
    private $menu;
    /**
     * @var string
     */
    private $photoPluginJsUrl;

	/**
     * @var PHOTO_BOL_PhotoAlbumService 
     */
    private $photoAlbumService;
	/**
     * @var OW_PluginManager
     */
    private $photoPlugin;
    /**
     * @var PHOTO_BOL_PhotoService 
     */
    private $photoService;	
	public function __construct()
	{
		$this->photoPlugin = OW::getPluginManager()->getPlugin('photo');
		$this->photoPluginJsUrl = $this->photoPlugin->getStaticJsUrl();
		$this->photoAlbumService = PHOTO_BOL_PhotoAlbumService::getInstance();
		$this->photoService = PHOTO_BOL_PhotoService::getInstance();
	    $this->menu = $this->getMenu();	
		if ( !OW::getRequest()->isAjax() )
        {
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'photo', 'photo');
        }	
	}

/**
     * Returns menu component
     *
     * @return BASE_CMP_ContentMenu
     */
    private function getMenu()
    {     
		$language = OW::getLanguage();
		
        $validLists = array('photo', 'album', 'tagged');
        $classes = array('', '', 'ow_ic_tag');     
		$urls = array(OW::getRouter()->urlForRoute('photo_list_index'), OW::getRouter()->urlForRoute('photo_list_albums'), ''); 
		$titles = array($language->text('advancedphoto', 'photos'), $language->text('advancedphoto', 'albums'), $language->text('photo', 'menu_tagged'));	

		if( $user = OW::getUser()->getUserObject()){
        	$validLists[3] = "myalbum";
        	$classes[3] = "";
        	$urls[3] = OW::getRouter()->urlForRoute('photo_user_albums', array('user' => $user->username));
        	$titles[3] = $language->text('advancedphoto', 'my_albums');
        }
		
        $checkPrivacy = PHOTO_BOL_PhotoService::getInstance()->countPhotos('featured');
        if ( !PHOTO_BOL_PhotoService::getInstance()->countPhotos('featured', $checkPrivacy) )
        {
            array_shift($validLists);
            array_shift($classes);
        }

        

        $menuItems = array();

        $order = 0;
        foreach ( $validLists as $type )
        {
            $item = new BASE_MenuItem();
            $item->setLabel($titles[$order]);
            $item->setUrl(($urls[$order] != '' ? $urls[$order] : OW::getRouter()->urlForRoute('view_photo_list', array('listType' => $type))));
            $item->setKey($type);
            $item->setIconClass($classes[$order]);
            $item->setOrder($order);

            array_push($menuItems, $item);

            $order++;
        }

        $menu = new BASE_CMP_ContentMenu($menuItems);

        return $menu;
    }
	
	public function viewList( array $params )
	{
		// is moderator
		$modPermissions = OW::getUser()->isAuthorized('photo');

		if ( !$modPermissions && !OW::getUser()->isAuthorized('photo', 'view') )
		{
			$this->setTemplate(OW::getPluginManager()->getPlugin('base')->getCtrlViewDir() . 'authorization_failed.html');
			return;
		}
		
		$params = array_merge($params, $_GET);
		$params['listType'] =  isset($params['listType']) ? $params['listType'] : 'latest';
		
		$this->assign('listType', $params['listType']);
		
		$el = $this->menu->getElement('photo');
		if ( $el )
        {
            $el->setActive(true);
        }
        
		$this->addComponent('photoMenu', $this->menu);
		
		// ajax
		if(isset($_GET['format']) && $_GET['format'] == 'json'){
			$params['idPrefix'] = '';
			$resp = $this->prepareMarkup($params);
			exit(json_encode($resp));			
		}
		
		$categories = array();
		foreach (ADVANCEDPHOTO_BOL_CategoryService::getInstance()->getCategoriesList() as $key => $item) {
			$categories[$key] = $item;
        }
        $this->assign('categories', $categories);
		
		//OW::getDocument()->setHeading(OW::getLanguage()->text('photo', 'page_title_browse_photos'));
        //OW::getDocument()->setHeadingIconClass('ow_ic_picture');
        
        OW::getDocument()->setTitle(OW::getLanguage()->text('photo', 'meta_title_photo_'.$params['listType']));
        OW::getDocument()->setDescription(OW::getLanguage()->text('photo', 'meta_description_photo_'.$params['listType']));
		
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticJsUrl() . 'hap.min.js');
		$script = '
			//var hap = new hap();
			hap.initialize({request_url: "", max_width: 220});			  
		';
		OW::getDocument()->addOnloadScript($script);
		
		
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl().'jquery.bbq.min.js');
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'photo.js');

		$script = '       
        $(window).bind( "hashchange", function(e) {
            var photo_id = $.bbq.getState("view-photo");
            if ( photo_id != undefined )
            {
                if ( window.photoFBLoading ) { return; }
                window.photoViewObj.showPhotoCmp(photo_id);
            }
        });';

		OW::getDocument()->addOnloadScript($script);
		
		
		$js = UTIL_JsGenerator::newInstance()
		->newVariable('addNewUrl', OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'index'))
		->jQueryEvent('#btn-add-new-photo', 'click', 'document.location.href = addNewUrl');

        OW::getDocument()->addOnloadScript($js);
	}
	

	private function prepareMarkup( array $params )
    {
		
        $cmp = new ADVANCEDPHOTO_CMP_PhotoList($params);
    
        /* @var $document OW_AjaxDocument */
        $document = OW::getDocument();

        $markup = array();
		$markup['is_next'] = ADVANCEDPHOTO_CTRL_Photo::$isNext;
		$markup['item_count'] = ADVANCEDPHOTO_CTRL_Photo::$item_count;
        $markup['body'] = $cmp->render();

        $onloadScript = $document->getOnloadScript();
        if ( !empty($onloadScript) )
        {
            $markup['onloadScript'] = $onloadScript;
        }
        
        $scriptFiles = $document->getScripts();
        if ( !empty($scriptFiles) )
        {
            $markup['scriptFiles'] = $scriptFiles;
        }

        $css = $document->getStyleDeclarations();
        if ( !empty($css) )
        {
            $markup['css'] = $css;
        }
       
        return $markup;
    }
	
	/**
     * Controller action for user albums list
     *
     * @param unknown_type $params
     */
    public function albums( array $params )
    {      
        // is moderator
        $modPermissions = OW::getUser()->isAuthorized('photo');

        if ( !OW::getUser()->isAuthorized('photo', 'view') && !$modPermissions)
        {
            $this->setTemplate(OW::getPluginManager()->getPlugin('base')->getCtrlViewDir() . 'authorization_failed.html');
            return;
        }	
        
		$params = array_merge($params, $_GET);
		$params['listType'] =  isset($params['listType']) ? $params['listType'] : 'latest';
		
		$this->assign('listType', $params['listType']);
		
		$el = $this->menu->getElement('album');
		if ( $el )
        {
            $el->setActive(true);
        }
        
		$this->addComponent('photoMenu', $this->menu);
		
		// ajax
		if(isset($_GET['format']) && $_GET['format'] == 'json'){
			$resp = $this->prepareAlbumMarkup($params);
			exit(json_encode($resp));			
		}
		
		$categories = array();
		foreach (ADVANCEDPHOTO_BOL_CategoryService::getInstance()->getCategoriesList() as $key => $item) {
			$categories[$key] = $item;
        }
        $this->assign('categories', $categories);
		
        OW::getDocument()->setTitle(OW::getLanguage()->text('advancedphoto', 'meta_title_photo_albums'));
		
		
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl().'jquery.bbq.min.js');
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'photo.js');
		
		$script = '       
        $(window).bind( "hashchange", function(e) {
            var photo_id = $.bbq.getState("view-photo");
            if ( photo_id != undefined )
            {
                if ( window.photoFBLoading ) { return; }
                window.photoViewObj.showPhotoCmp(photo_id);
            }
        });';

		OW::getDocument()->addOnloadScript($script);
		
		/*OW::getLanguage()->addKeyForJs('photo', 'tb_edit_photo');
		OW::getLanguage()->addKeyForJs('photo', 'confirm_delete');
		OW::getLanguage()->addKeyForJs('photo', 'mark_featured');
		OW::getLanguage()->addKeyForJs('photo', 'remove_from_featured');
		
		$objParams = array(
			'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
			'fbResponder' => OW::getRouter()->urlForRoute('photo.floatbox')
		);

		$script = '$("div.photo a").on("click", function(e){;
			e.preventDefault();
			var photo_id = $(this).attr("rel");

			if ( !window.photoViewObj ) {
				window.photoViewObj = new photoView('.json_encode($objParams).');
			}
			
			window.photoViewObj.setId(photo_id);
		}); ';
		OW::getDocument()->addOnloadScript($script);		*/
		
		$js = UTIL_JsGenerator::newInstance()
			->newVariable('addNewUrl', OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'index'))
			->jQueryEvent('#btn-add-new-photo', 'click', 'document.location.href = addNewUrl');

        OW::getDocument()->addOnloadScript($js);
		
    }
	
	private function prepareAlbumMarkup( array $params )
    {

        $cmp = new ADVANCEDPHOTO_CMP_AlbumList($params);
    
        /* @var $document OW_AjaxDocument */
        $document = OW::getDocument();

        $markup = array();
		$markup['is_next'] = ADVANCEDPHOTO_CTRL_Photo::$isNextAlbum;
		$markup['item_count'] = ADVANCEDPHOTO_CTRL_Photo::$item_count_album;
        $markup['body'] = $cmp->render();

        $onloadScript = $document->getOnloadScript();
        if ( !empty($onloadScript) )
        {
            $markup['onloadScript'] = $onloadScript;
        }
        
        $scriptFiles = $document->getScripts();
        if ( !empty($scriptFiles) )
        {
            $markup['scriptFiles'] = $scriptFiles;
        }

        $css = $document->getStyleDeclarations();
        if ( !empty($css) )
        {
            $markup['css'] = $css;
        }
       
        return $markup;
    }
	
	/**
     * Tagged photo list action
     *
     * @param array $params
     */
    public function viewTaggedList( array $params = null )
    {
        if ( isset($params['tag']) )
        {
            $tag = htmlspecialchars(urldecode($params['tag']));
        }
        
        // is moderator
        $modPermissions = OW::getUser()->isAuthorized('photo');
        
        if ( !$modPermissions && !OW::getUser()->isAuthorized('photo', 'view') )
        {
            $this->setTemplate(OW::getPluginManager()->getPlugin('base')->getCtrlViewDir() . 'authorization_failed.html');
            return;
        }

        $this->addComponent('photoMenu', $this->menu);

        $this->menu->getElement('tagged')->setActive(true);

        $this->setTemplate(OW::getPluginManager()->getPlugin('advancedphoto')->getCtrlViewDir() . 'photo_view_list-tagged.html');

        $listUrl = OW::getRouter()->urlForRoute('view_tagged_photo_list_st');

        OW::getDocument()->addScript($this->photoPluginJsUrl . 'photo_tag_search.js');

        $objParams = array(
            'listUrl' => $listUrl
        );

        $script =
            "$(document).ready(function(){
                var photoSearch = new photoTagSearch(" . json_encode($objParams) . ");
            }); ";

        OW::getDocument()->addOnloadScript($script);
        if ( isset($tag) )
        {
            $this->assign('tag', $tag);
            OW::getDocument()->setTitle(OW::getLanguage()->text('photo', 'meta_title_photo_tagged_as', array('tag' => $tag)));
            OW::getDocument()->setDescription(OW::getLanguage()->text('photo', 'meta_description_photo_tagged_as', array('tag' => $tag)));
			OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticJsUrl() . 'hap.min.js');
			$script = '
				hap.initialize({request_url: "'.  OW::getRouter()->urlForRoute('photo_list_index') .'", max_width: 220, listType: "tagged", tag: "'. $tag .'"});			  
			';
			OW::getDocument()->addOnloadScript($script);
			
			OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl().'jquery.bbq.min.js');
			OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'photo.js');
			
			OW::getLanguage()->addKeyForJs('photo', 'tb_edit_photo');
			OW::getLanguage()->addKeyForJs('photo', 'confirm_delete');
			OW::getLanguage()->addKeyForJs('photo', 'mark_featured');
			OW::getLanguage()->addKeyForJs('photo', 'remove_from_featured');
			
			$objParams = array(
				'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
				'fbResponder' => OW::getRouter()->urlForRoute('photo.floatbox')
			);
			
			$script = '$("div.photo a").on("click", function(e){
				e.preventDefault();
				var photo_id = $(this).attr("rel");

				if ( !window.photoViewObj ) {
					window.photoViewObj = new photoView('.json_encode($objParams).');
				}
				
				window.photoViewObj.setId(photo_id);
			}); 
			
			$(window).bind( "hashchange", function(e) {
				var photo_id = $.bbq.getState("view-photo");
				if ( photo_id != undefined )
				{
					if ( window.photoFBLoading ) { return; }
					window.photoViewObj.showPhotoCmp(photo_id);
				}
			});';
			
			OW::getDocument()->addOnloadScript($script);
			
        }
        else
        {
            $tags = new BASE_CMP_EntityTagCloud('photo');
            $tags->setRouteName('view_tagged_photo_list');
            $this->addComponent('tags', $tags);
            
            OW::getDocument()->setTitle(OW::getLanguage()->text('photo', 'meta_title_photo_tagged'));
            $tagsArr = BOL_TagService::getInstance()->findMostPopularTags('photo', 20);
            foreach ( $tagsArr as $t )
            {
                $labels[] = $t['label'];
            }
            $tagStr = $tagsArr ? implode(', ', $labels) : '';
            OW::getDocument()->setDescription(OW::getLanguage()->text('photo', 'meta_description_photo_tagged', array('topTags' => $tagStr)));
        }

        $this->assign('listType', 'tagged');
		
        //OW::getDocument()->setHeading(OW::getLanguage()->text('photo', 'page_title_browse_photos'));
        //OW::getDocument()->setHeadingIconClass('ow_ic_picture');
		
		$js = UTIL_JsGenerator::newInstance()
			->newVariable('addNewUrl', OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'index'))
			->jQueryEvent('#btn-add-new-photo', 'click', 'document.location.href = addNewUrl');

        OW::getDocument()->addOnloadScript($js);
    }
	/**
     * Controller action for user albums list
     *
     * @param unknown_type $params
     */
    public function userAlbums( array $params )
    {
        if ( empty($params['user']) || !mb_strlen($username = trim($params['user'])) )
        {
            throw new Redirect404Exception();
        }
        
        $user = BOL_UserService::getInstance()->findByUsername($username);
        if ( !$user )
        {
            throw new Redirect404Exception();
        }
        
        $userId = $user->id; 
        $ownerMode = $userId == OW::getUser()->getId();
        
        // is moderator
        $modPermissions = OW::getUser()->isAuthorized('photo');

        if ( !OW::getUser()->isAuthorized('photo', 'view') && !$modPermissions && !$ownerMode )
        {
            $this->setTemplate(OW::getPluginManager()->getPlugin('base')->getCtrlViewDir() . 'authorization_failed.html');
            return;
        }

        // permissions check
        if ( !$ownerMode && !$modPermissions )
        {
            $privacyParams = array('action' => 'photo_view_album', 'ownerId' => $userId, 'viewerId' => OW::getUser()->getId());
            $event = new OW_Event('privacy_check_permission', $privacyParams);
            OW::getEventManager()->trigger($event);
        }
        
        
        /*$el = $this->menu->getElement('myalbum');
		if ( $el && OW::getUser()->getUserObject()->username == $username)
        {
            $el->setActive(true);
        }else{
        	 $el->setActive(false);
        }*/
        
		$this->addComponent('photoMenu', $this->menu);
        
        
        $this->assign('username', $username);
        $displayName = BOL_UserService::getInstance()->getDisplayName($userId);
        $this->assign('displayName', $displayName);

        $total = $this->photoAlbumService->countUserAlbums($userId);
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        $config = OW::getConfig();
        $albumPerPage = $config->getValue('photo', 'photos_per_page');

        $albums = $this->photoAlbumService->findUserAlbumList($userId, $page, $albumPerPage);
        $this->assign('albums', $albums);
        $this->assign('total', $total);
        $this->assign('userId', $userId);

        // Paging
        $pages = (int) ceil($total / $albumPerPage);
        $paging = new BASE_CMP_Paging($page, $pages, $albumPerPage);
        $this->assign('paging', $paging->render());

        $this->assign('widthConfig', $config->getValue('photo', 'preview_image_width'));
        $this->assign('heightConfig', $config->getValue('photo', 'preview_image_height'));

        OW::getDocument()->setHeading(
            OW::getLanguage()->text('photo', 'page_title_user_albums', array('user' => $displayName))
        );

        OW::getDocument()->setHeadingIconClass('ow_ic_picture');
        OW::getDocument()->setTitle(OW::getLanguage()->text('photo', 'meta_title_photo_useralbums', array('displayName' => $displayName)));
        
        if ( $albums )
        {
            $albumTitles = array(); 
            $i = 0;
            foreach ( $albums as $album )
            {
                $albumTitles[] = $album['dto']->name;
                if ( $i == 10 )
                {
                    break;
                }
                $i++;
            }
            $albumTitles = implode(', ', $albumTitles);
            OW::getDocument()->setDescription(
                OW::getLanguage()->text('photo', 'meta_description_photo_useralbums', array('displayName' => $displayName, 'albums' => $albumTitles))
            );
        }
		$js = UTIL_JsGenerator::newInstance()
			->newVariable('addNewUrl', OW::getRouter()->urlFor('PHOTO_CTRL_Upload', 'index'))
			->jQueryEvent('#btn-add-new-photo', 'click', 'document.location.href = addNewUrl');

        OW::getDocument()->addOnloadScript($js);				
    }
	
	/**
     * Controller action for user album
     *
     * @param array $params
     */
    public function userAlbum( array $params )
    {
        if ( !isset($params['user']) || !strlen($user = trim($params['user'])) )
        {
            throw new Redirect404Exception();
        }

        if ( !isset($params['album']) || !($albumId = (int) $params['album']) )
        {
            throw new Redirect404Exception();
        }

        // is owner
        $userDto = BOL_UserService::getInstance()->findByUsername($user);
        
        if ( $userDto )
        {
            $ownerMode = $userDto->id == OW::getUser()->getId();
        }
        else 
        {
            $ownerMode = false;
        }
        
        // is moderator
        $modPermissions = OW::getUser()->isAuthorized('photo');

        if ( !OW::getUser()->isAuthorized('photo', 'view') && !$modPermissions && !$ownerMode )
        {
            $this->setTemplate(OW::getPluginManager()->getPlugin('base')->getCtrlViewDir() . 'authorization_failed.html');
            return;
        }
                
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        $config = OW::getConfig();
        $photoPerPage = $config->getValue('photo', 'photos_per_page');

        $album = $this->photoAlbumService->findAlbumById($albumId);

        if ( !$album )
        {
            throw new Redirect404Exception();
            return;
        }

        $this->assign('album', $album);
        
        // permissions check
        if ( !$ownerMode && !$modPermissions )
        {
            $privacyParams = array('action' => 'photo_view_album', 'ownerId' => $album->userId, 'viewerId' => OW::getUser()->getId());
            $event = new OW_Event('privacy_check_permission', $privacyParams);
            OW::getEventManager()->trigger($event);
        }

        $this->assign('userName', BOL_UserService::getInstance()->getUserName($album->userId));
        $displayName = BOL_UserService::getInstance()->getDisplayName($album->userId);
        $this->assign('displayName', $displayName);

        $photos = $this->photoService->getAlbumPhotos($albumId, $page, $photoPerPage);
		$aPhotos = array();
		if ( $photos )
		{
			$userIds = array();
			foreach ( $photos as $photo )
			{
				$photo['fullurl'] = $this->photoService->getPhotoUrl($photo['id']);
				$photo['comments_count'] = BOL_CommentService::getInstance()->findCommentCount('photo_comments', $photo['id']);
				$aPhotos[] = $photo;
			}

		}

        $this->assign('photos', $aPhotos);

        $total = $this->photoAlbumService->countAlbumPhotos($albumId);
        $this->assign('total', $total);

        $lastUpdated = $this->photoAlbumService->getAlbumUpdateTime($albumId);
        $this->assign('lastUpdate', $lastUpdated);

        $this->assign('widthConfig', $config->getValue('photo', 'preview_image_width'));
        $this->assign('heightConfig', $config->getValue('photo', 'preview_image_height'));

        // Paging
        $pages = (int) ceil($total / $photoPerPage);
        $paging = new BASE_CMP_Paging($page, $pages, $photoPerPage);
        $this->assign('paging', $paging->render());

        OW::getDocument()->setHeading(
            $album->name .
            ' <span class="ow_small">' .
            OW::getLanguage()->text('photo', 'photos_in_album', array('total' => $total)) .
            '</span>'
        );

        OW::getDocument()->setHeadingIconClass('ow_ic_picture');

        // check permissions
        $canEdit = OW::getUser()->isAuthorized('photo', 'upload', $album->userId);
        $canModerate = OW::getUser()->isAuthorized('photo');

        $authorized = $canEdit || $canModerate;
        $this->assign('authorized', $canEdit || $canModerate);
        $this->assign('canUpload', $canEdit);

        $lang = OW::getLanguage();

        if ( $authorized )
        {
            $albumEditForm = new albumEditForm();
            $albumEditForm->getElement('albumName')->setValue($album->name);
            $albumEditForm->getElement('category')->setValue($album->category_id);
            $albumEditForm->getElement('id')->setValue($album->id);

            $this->addForm($albumEditForm);

            OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'album.js');

            if ( OW::getRequest()->isPost() && $albumEditForm->isValid($_POST) )
            {
                $res = $albumEditForm->process();
                if ( $res['result'] )
                {
                    OW::getFeedback()->info($lang->text('photo', 'photo_album_updated'));
                    $this->redirect();
                }
            }

            $lang->addKeyForJs('photo', 'confirm_delete_album');
            $lang->addKeyForJs('photo', 'edit_album');

            $objParams = array(
                'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
                'albumId' => $albumId,
                'uploadUrl' => OW::getRouter()->urlForRoute('photo_upload_album', array('album' => $album->id))
            );

            $script =
                "$(document).ready(function(){
                    var album = new photoAlbum( " . json_encode($objParams) . ");
                }); ";

            OW::getDocument()->addOnloadScript($script);
        }
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticJsUrl() . 'hap.min.js');
		$script = '
			//var hap = new hap();
			hap.initialize({request_url: "", max_width: 220});			  
		';
		OW::getDocument()->addOnloadScript($script);
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl().'jquery.bbq.min.js');
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('photo')->getStaticJsUrl() . 'photo.js');
        
        OW::getLanguage()->addKeyForJs('photo', 'tb_edit_photo');
        OW::getLanguage()->addKeyForJs('photo', 'confirm_delete');
        OW::getLanguage()->addKeyForJs('photo', 'mark_featured');
        OW::getLanguage()->addKeyForJs('photo', 'remove_from_featured');
        
        $objParams = array(
            'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
            'fbResponder' => OW::getRouter()->urlForRoute('photo.floatbox')
        );
        
		$script = '$("div.photo a").on("click", function(e){
			e.preventDefault();
			var photo_id = $(this).attr("rel");

			if ( !window.photoViewObj ) {
				window.photoViewObj = new photoView('.json_encode($objParams).');
			}
			
			window.photoViewObj.setId(photo_id);
		}); 
        
        $(window).bind( "hashchange", function(e) {
            var photo_id = $.bbq.getState("view-photo");
            if ( photo_id != undefined )
            {
                if ( window.photoFBLoading ) { return; }
                window.photoViewObj.showPhotoCmp(photo_id);
            }
        });';
        
        OW::getDocument()->addOnloadScript($script);
        
        OW::getDocument()->setTitle(
            $lang->text('photo', 'meta_title_photo_useralbum', array('displayName' => $displayName, 'albumName' => $album->name))
        );
        OW::getDocument()->setDescription(
            $lang->text('photo', 'meta_description_photo_useralbum', array('displayName' => $displayName, 'number' => $total))
        );
    }
}

/**
 * Album edit form class
 */
class albumEditForm extends Form
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct('albumEditForm');

        $language = OW::getLanguage();

        // album id field
        $albumIdField = new HiddenField('id');
        $albumIdField->setRequired(true);
        $this->addElement($albumIdField);

        // album name Field
        $albumNameField = new TextField('albumName');

        $this->addElement($albumNameField->setLabel($language->text('photo', 'album')));
		
        // category
        $categoryField = new Selectbox('category');
    	$categories = array();
		foreach (ADVANCEDPHOTO_BOL_CategoryService::getInstance()->getCategoriesList() as $key => $item) {
			$categories[$item->id] = $item->name;
        }
        $categoryField->setOptions($categories);
        $this->addElement($categoryField->setLabel($language->text('advancedphoto', 'category')));
        
        $submit = new Submit('save');
        $submit->setValue($language->text('photo', 'btn_edit'));
        $this->addElement($submit);
    }

    /**
     * Updates photo album
     *
     * @return boolean
     */
    public function process()
    {
        $values = $this->getValues();

        $albumService = PHOTO_BOL_PhotoAlbumService::getInstance();

        $language = OW::getLanguage();

        if ( isset($values['id']) && ($albumId = (int) $values['id']) )
        {
            $album = $albumService->findAlbumById($albumId);

            if ( $album )
            {
                if ( strlen($albumName = htmlspecialchars(trim($values['albumName']))) )
                {
                    $album->name = $albumName;
                    $album->category_id = (int) $values['category'];

                    if ( $albumService->updateAlbum($album) )
                    {
                        return array('result' => true, 'id' => $album->id);
                    }
                }
            }
        }
        else
        {
            return array('result' => false, 'id' => $album->id);
        }

        return false;
    }

    private function save() {
        
    }
}