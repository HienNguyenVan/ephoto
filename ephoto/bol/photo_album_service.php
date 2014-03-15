<?php

final class ADVANCEDPHOTO_BOL_PhotoAlbumService
{
    /**
     * @var ADVANCEDPHOTO_BOL_PhotoAlbumDao
     */
    private $advancedphotoAlbumDao;
    /**
     * @var ADVANCEDPHOTO_BOL_PhotoDao
     */
    private $advancedphotoDao;
	/**
     * @var PHOTO_BOL_PhotoAlbumService
     */
    private $albumService;
    /**
     * Class instance
     *
     * @var PHOTO_BOL_PhotoAlbumService
     */
    private static $classInstance;
	/**
     * Returns class instance
     *
     * @return PHOTO_BOL_PhotoAlbumService
     */
    public static function getInstance()
    {
        if ( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }
    /**
     * Class constructor
     *
     */
    private function __construct()
    {
        $this->advancedphotoAlbumDao = ADVANCEDPHOTO_BOL_PhotoAlbumDao::getInstance();
        $this->advancedphotoDao = ADVANCEDPHOTO_BOL_PhotoDao::getInstance();
		$this->albumService = PHOTO_BOL_PhotoAlbumService::getInstance();
    }
    /**
     * Counts user albums
     *
     * @param string $listType
     * @return int
     */
    public function countAlbums( $listType, $search = '' )
    {
        return $this->advancedphotoAlbumDao->countAlbums($listType, $search);
    }

    /**
     * Returns user's photo albums list
     *
     * @param string $listType
     * @param int $page
     * @param int $limit
     * @return array of PHOTO_BOL_PhotoAlbum
     */
    public function findAlbumList( $listType, $page, $limit, $search = '' )
    {
        $albums = $this->advancedphotoAlbumDao->getAlbumList($listType, $page, $limit, $search);

        $list = array();

        if ( $albums )
        {
            $albumIdList = array();
            foreach ( $albums as $key => $album )
            {
                array_push($albumIdList, $album['id']);
                $list[$key]['dto'] = $album;
            }
            
            $covers = $this->albumService->getAlbumCoverForList($albumIdList);
            $counters = $this->albumService->countAlbumPhotosForList($albumIdList);
            foreach ( $albums as $key => $album )
            {
                $list[$key]['cover'] = $covers[$album['id']];
                $list[$key]['photo_count'] = $counters[$album['id']];
            }
        }

        return $list;
    }
}