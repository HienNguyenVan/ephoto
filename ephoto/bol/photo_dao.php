<?php

class ADVANCEDPHOTO_BOL_PhotoDao extends OW_BaseDao
{
    /**
     * Singleton instance.
     *
     * @var PHOTO_BOL_PhotoDao
     */
    private static $classInstance;

    const PHOTO_PREFIX = 'photo_';

    const PHOTO_PREVIEW_PREFIX = 'photo_preview_';

    const PHOTO_ORIGINAL_PREFIX = 'photo_original_';
    
    const CACHE_TAG_PHOTO_LIST = 'photo.list';

    /**
     * Constructor.
     *
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns an instance of class.
     *
     * @return PHOTO_BOL_PhotoDao
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    /**
     * @see OW_BaseDao::getDtoClassName()
     *
     */
    public function getDtoClassName()
    {
        return 'PHOTO_BOL_Photo';
    }

    /**
     * @see OW_BaseDao::getTableName()
     *
     */
    public function getTableName()
    {
        return OW_DB_PREFIX . 'photo';
    }

    

    /**
     * Get photo list (featured|latest|toprated)
     *
     * @param string $category
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPhotoListCategory($category, $page, $limit, $checkPrivacy)
    {
        $limit = (int) $limit;
        $first = ( $page - 1 ) * $limit;

        $albumDao = PHOTO_BOL_PhotoAlbumDao::getInstance();
        
        $privacyCond = $checkPrivacy ? " AND `p`.`privacy` = 'everybody' " : "";

       $query = "
                    SELECT `p`.*, `a`.`userId`
                    FROM `" . $this->getTableName() . "` AS `p`
                    LEFT JOIN `" . $albumDao->getTableName() . "` AS `a` ON ( `p`.`albumId` = `a`.`id` )
                    WHERE `p`.`status` = 'approved' ".$privacyCond." AND `a`.`category_id` = :category
                    ORDER BY `p`.`id` DESC
                    LIMIT :first, :limit";
        
        $qParams = array('first' => $first, 'limit' => $limit, 'category' => $category);
        
        $cacheLifeTime = $first == 0 ? 24 * 3600 : null;
        $cacheTags = $first == 0 ? array(self::CACHE_TAG_PHOTO_LIST) : null;
        
        return $this->dbo->queryForList($query, $qParams, $cacheLifeTime, $cacheTags);
    }
	
	 /**
     * Count photos
     *
     * @param string $category
     * @param boolean $checkPrivacy
     * @return int
     */
    public function countPhotos( $category, $checkPrivacy = true )
    {
        $privacyCond = $checkPrivacy ? " AND `p`.`privacy` = 'everybody' " : "";
		$photoAlbumDao = PHOTO_BOL_PhotoAlbumDao::getInstance();
	
		$query = "
            SELECT COUNT(`p`.`id`)       
            FROM `" . $this->getTableName() . "` AS `p`
            LEFT JOIN `" . $photoAlbumDao->getTableName() . "` AS `a` ON ( `a`.`id` = `p`.`albumId` )
            WHERE `a`.`category_id` = " . (int) $category ."
        ";

		return $this->dbo->queryForColumn($query);              
    }
	
	/**
     * Get photo list (featured|latest|toprated)
     *
     * @param string $listtype
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPhotoList( $listtype, $page, $limit, $checkPrivacy = true )
    {
        $config = OW::getConfig();
		$limit = (int) $config->getValue('advancedphoto', 'photofeature_per_page');
        $first = ( $page - 1 ) * $limit;

        $albumDao = PHOTO_BOL_PhotoAlbumDao::getInstance();
        
        $privacyCond = $checkPrivacy ? " AND `p`.`privacy` = 'everybody' " : "";

        switch ( $listtype )
        {
            case 'featured':
                $photoFeaturedDao = PHOTO_BOL_PhotoFeaturedDao::getInstance();
				$config = OW::getConfig();
                $query = "
                    SELECT `p`.*, `a`.`userId`
                    FROM `" . $this->getTableName() . "` AS `p`
                    LEFT JOIN `" . $albumDao->getTableName() . "` AS `a` ON ( `p`.`albumId` = `a`.`id` )
                    LEFT JOIN `" . $photoFeaturedDao->getTableName() . "` AS `f` ON (`f`.`photoId`=`p`.`id`)
                    WHERE `p`.`status` = 'approved' ".$privacyCond." AND `f`.`id` IS NOT NULL
                    ORDER BY RAND() 
                    LIMIT :first, :limit";

                break;

            case 'latest':

                $query = "
		            SELECT `p`.*, `a`.`userId`
		            FROM `" . $this->getTableName() . "` AS `p`
		            LEFT JOIN `" . $albumDao->getTableName() . "` AS `a` ON ( `p`.`albumId` = `a`.`id` )
		            WHERE `p`.`status` = 'approved' ".$privacyCond."
		            ORDER BY `p`.`id` DESC
		            LIMIT :first, :limit";

                break;
        }
		
        $qParams = array('first' => $first, 'limit' => $limit);
        
        $cacheLifeTime = $first == 0 ? 24 * 3600 : null;
        $cacheTags = $first == 0 ? array(self::CACHE_TAG_PHOTO_LIST) : null;
        
        return $this->dbo->queryForList($query, $qParams, $cacheLifeTime, $cacheTags);
    }
	
	/**
     * Count photos
     *
     * @param string $listtype
     * @param boolean $checkPrivacy
     * @return int
     */
    public function countPhotosFeature( $listtype, $checkPrivacy = true )
    {
        $privacyCond = $checkPrivacy ? " AND `p`.`privacy` = 'everybody' " : "";
        
        switch ( $listtype )
        {
            case 'featured':
                $featuredDao = PHOTO_BOL_PhotoFeaturedDao::getInstance();

                $query = "
                    SELECT COUNT(`p`.`id`)       
                    FROM `" . $this->getTableName() . "` AS `p`
                    LEFT JOIN `" . $featuredDao->getTableName() . "` AS `f` ON ( `p`.`id` = `f`.`photoId` )
                    WHERE `p`.`status` = 'approved' ".$privacyCond." AND `f`.`id` IS NOT NULL
                ";

                return $this->dbo->queryForColumn($query);

                break;

            case 'latest':
                $example = new OW_Example();

                $example->andFieldEqual('status', 'approved');
                if ( $checkPrivacy )
                {
                    $example->andFieldEqual('privacy', 'everybody');
                }

                return $this->countByExample($example);

                break;
        }
    }
}