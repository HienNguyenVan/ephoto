<?php

class ADVANCEDPHOTO_CMP_PhotoList extends OW_Component
{
    /**
     * @var PHOTO_BOL_PhotoService 
     */
    private $photoService;
	
	/**
     * @var ADVANCEDPHOTO_BOL_PhotoService 
     */
    private $advancePhotoService;
	
    /**
     * Class constructor
     *
     * @param string $listType
     * @param int $count
     * @param string $tag
     */
    public function __construct( array $params)
    {

        parent::__construct();
       	$listType = $params['listType'];
		$this->assign('listType', $listType);
		$this->assign('idPrefix', $params['idPrefix']);
		$this->assign('format', isset($params['format'])? $params['format'] : '');
		$this->photoService = PHOTO_BOL_PhotoService::getInstance();
		$this->advancePhotoService = ADVANCEDPHOTO_BOL_PhotoService::getInstance();
		
		$page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;
	
		$config = OW::getConfig();

		$photosPerPage = $config->getValue('photo', 'photos_per_page');
		
		$result = array();
		
		$photos = array();
		if ( isset($params['tag']) && strlen($tag = $params['tag']) )
		{
			$photos = $this->photoService->findTaggedPhotos($tag, $page, $photosPerPage);
			$records = $this->photoService->countTaggedPhotos($tag);
		}else if (is_numeric($listType)){
			$checkPrivacy = !OW::getUser()->isAuthorized('photo');
			$photos = $this->advancePhotoService->getPhotoListCategory($listType, $page, $photosPerPage, $checkPrivacy);
			$records = $this->advancePhotoService->countPhotoListCategory($listType, $checkPrivacy);
		}else if($listType == 'featured'){
			$checkPrivacy = false;
			$photosPerPage = OW::getConfig()->getValue('advancedphoto', 'photofeature_per_page');
			$photos = $this->advancePhotoService->findPhotoList($listType, $page, $photosPerPage, $checkPrivacy);
			$records = $this->advancePhotoService->countPhotosFeature($listType, $checkPrivacy);
		}
		else
		{
			//echo $listType;die;
			$checkPrivacy = $listType == 'latest' && !OW::getUser()->isAuthorized('photo');
			$photos = $this->photoService->findPhotoList($listType, $page, $photosPerPage, $checkPrivacy);
			$records = $this->photoService->countPhotos($listType, $checkPrivacy);
		}
		
		$aPhotos = array();
		if ( $photos )
		{
			$userIds = array();
			foreach ( $photos as $photo )
			{
				if ( !in_array($photo['userId'], $userIds) )
				array_push($userIds, $photo['userId']);
				$photo['url'] = $this->photoService->getPhotoUrl($photo['id']);
				$album = PHOTO_BOL_PhotoAlbumService::getInstance()->findAlbumById($photo['albumId']);
				$ownerName = BOL_UserService::getInstance()->getUserName($album->userId);
				$photo['album_title'] = $album->name;
				$photo['album_href'] = OW::getRouter()->urlForRoute('photo_user_album', array('user' => $ownerName, 'album' => $album->id));
				$aPhotos[] = $photo;
			}

			$names = BOL_UserService::getInstance()->getDisplayNamesForList($userIds);
			$this->assign('names', $names);
			$usernames = BOL_UserService::getInstance()->getUserNamesForList($userIds);
			$this->assign('usernames', $usernames);

			// Paging
			$pages = (int) ceil($records / $photosPerPage);
			
			ADVANCEDPHOTO_CTRL_Photo::$isNext = $result['isNext'] = $isNext = ($pages > $page) ? true : false;
			ADVANCEDPHOTO_CTRL_Photo::$item_count = $result['item_count'] = count($aPhotos);
			
			
			$this->assign('photos', $aPhotos);
			$this->assign('no_content', false);
		}
		else
		{
			$this->assign('no_content', true);
		}
		
		if(OW::getPluginManager()->isPluginActive('gphotoviewer')){
			$script = "PhotoViewer.bindPhotoViewer();";
			OW::getDocument()->addOnloadScript($script);
		}else{
			$objParams = array(
				'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
				'fbResponder' => OW::getRouter()->urlForRoute('photo.floatbox')
			);
			
			OW::getLanguage()->addKeyForJs('photo', 'tb_edit_photo');
			OW::getLanguage()->addKeyForJs('photo', 'confirm_delete');
			OW::getLanguage()->addKeyForJs('photo', 'mark_featured');
			OW::getLanguage()->addKeyForJs('photo', 'remove_from_featured');
			
			$script = '$("div.photo a").on("click", function(e){
				e.preventDefault();
				var photo_id = $(this).attr("rel");
				if ( !window.photoViewObj ) {
					window.photoViewObj = new photoView('.json_encode($objParams).');
				}
				window.photoViewObj.setId(photo_id);
			}); ';
			OW::getDocument()->addOnloadScript($script);
		}
    }
}