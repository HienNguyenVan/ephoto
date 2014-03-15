<?php

class ADVANCEDPHOTO_CMP_AlbumList extends OW_Component
{
	
	/**
     * @var ADVANCEDPHOTO_BOL_AlbumService 
     */
    private $advanceAlbumService;
	
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
		$this->assign('format', isset($params['format'])? $params['format'] : '');
		$this->advanceAlbumService = ADVANCEDPHOTO_BOL_PhotoAlbumService::getInstance();
		
		$serach = isset($params['search']) ? $params['search'] : '';
		
		$total = $this->advanceAlbumService->countAlbums($listType, $serach);
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        $config = OW::getConfig();
        $albumPerPage = $config->getValue('photo', 'photos_per_page');

        $albums = $this->advanceAlbumService->findAlbumList($listType, $page, $albumPerPage, $serach);
		$aAlbums = array();
		if ( $albums )
		{
			$userIds = array();
			foreach ( $albums as $album )
			{
				if ( !in_array($album['dto']['userId'], $userIds) )
				array_push($userIds, $album['dto']['userId']);
				$aAlbums[] = $album;
			}
						
			$names = BOL_UserService::getInstance()->getDisplayNamesForList($userIds);
			$this->assign('names', $names);
			$usernames = BOL_UserService::getInstance()->getUserNamesForList($userIds);
			$this->assign('usernames', $usernames);
			
			$this->assign('albums', $aAlbums);
			$this->assign('total', $total);

			// Paging
			$pages = (int) ceil($total / $albumPerPage);
		   

			$this->assign('widthConfig', $config->getValue('photo', 'preview_image_width'));
			$this->assign('heightConfig', $config->getValue('photo', 'preview_image_height'));
			
			
			ADVANCEDPHOTO_CTRL_Photo::$isNextAlbum = $result['isNext'] = $isNext = ($pages > $page) ? true : false;
			ADVANCEDPHOTO_CTRL_Photo::$item_count_album = $result['item_count'] = count($aAlbums);

			$this->assign('no_content', false);
		}
		else
		{
			$this->assign('no_content', true);
		}
    }
}