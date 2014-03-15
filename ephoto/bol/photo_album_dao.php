<?php
class ADVANCEDPHOTO_BOL_PhotoAlbumDao extends OW_BaseDao
{
	const CACHE_TAG_ALBUM_LIST = 'album.list';
    /**
     * Constructor.
     *
     */
    protected function __construct()
    {
        parent::__construct();
    }
    /**
     * Singleton instance.
     *
     * @var ADVANCEDPHOTO_BOL_PhotoAlbumDao
     */
    private static $classInstance;

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return ADVANCEDPHOTO_BOL_PhotoAlbumDao
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
        return 'ADVANCEDPHOTO_BOL_PhotoAlbum';
    }

    /**
     * @see OW_BaseDao::getTableName()
     *
     */
    public function getTableName()
    {
        return OW_DB_PREFIX . 'photo_album';
    }

    /**
     * Count albums added by a user
     *
     * @param string $listType
	 * @param string $search
     * @return int
     */
    public function countAlbums( $listType, $search = '')
    {
		$condition = '';
		if(is_numeric($listType) && $listType != 0) $condition .= " AND`a`.category_id = ". $listType;
		if(!empty($search)) $condition .= " AND `a`.name like '%". $search ."%'";
		$query = "
			SELECT COUNT(`a`.`id`)       
			FROM `" . $this->getTableName() . "` AS `a`
			WHERE 1 = 1 ". $condition ."
		";

		return $this->dbo->queryForColumn($query);
    }

    /**
     * Get the list of user albums
     *
     * @param string $listType
     * @param int $page
     * @param int $limit
     * @return array of ADVANCEDPHOTO_BOL_PhotoAlbum
     */
    public function getAlbumList( $listType, $page, $limit, $search = '' )
    {
		$limit = (int) $limit;
        $first = ( $page - 1 ) * $limit;
		$condition = '';
		if(is_numeric($listType) && $listType != 0) $condition .= " AND`a`.category_id = ". $listType;
		if(!empty($search)) $condition .= " AND `a`.name like '%". $search ."%'";
		
		$query = "
			SELECT `a`.*     
			FROM `" . $this->getTableName() . "` AS `a`
			WHERE 1 = 1 ". $condition ."
			ORDER BY `a`.`id` DESC
			LIMIT :first, :limit";

        $qParams = array('first' => $first, 'limit' => $limit);
        
        $cacheLifeTime = $first == 0 ? 24 * 3600 : null;
        $cacheTags = $first == 0 ? array(self::CACHE_TAG_ALBUM_LIST) : null;
        
        return $this->dbo->queryForList($query, $qParams, $cacheLifeTime, $cacheTags);		
    }
    
}