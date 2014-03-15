<?php

final class ADVANCEDPHOTO_BOL_PhotoService
{
    const EVENT_AFTER_DELETE = 'photo.after_delete';
    const EVENT_AFTER_EDIT = 'photo.after_edit';

    /**
     * @var PHOTO_BOL_PhotoDao
     */
    private $photoDao;
	
	/**
     * @var PHOTO_BOL_PhotoService
     */
    private $photoService;
	
	/**
     * @var ADVANCEDPHOTO_BOL_PhotoDao
     */
	private $advancedphotoDao;
	
    /**
     * @var PHOTO_BOL_PhotoFeaturedDao
     */
    private $photoFeaturedDao;
    /**
     * Class instance
     *
     * @var PHOTO_BOL_PhotoService
     */
    private static $classInstance;

    /**
     * Class constructor
     *
     */
    private function __construct()
    {
        $this->advancedphotoDao = ADVANCEDPHOTO_BOL_PhotoDao::getInstance();
		$this->photoDao = PHOTO_BOL_PhotoDao::getInstance();
		$this->photoService = PHOTO_BOL_PhotoService::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return PHOTO_BOL_PhotoService
     */
    public static function getInstance()
    {
        if ( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }


    public function getPhotoListCategory( $category, $page, $limit, $checkPrivacy)
    {
        
        $photos = $this->advancedphotoDao->getPhotoListCategory($category, $page, $limit, $checkPrivacy);

        if ( $photos )
        {
            foreach ( $photos as $key => $photo )
            {
                $photos[$key]['url'] = $this->photoService->getPhotoPreviewUrl($photo['id']);
            }
        }

        return $photos;
    }

	/**
     * Counts photos
     *
     * @param string $category
     * @return int
     */
    public function countPhotoListCategory( $category, $checkPrivacy = true )
    {
        return $this->advancedphotoDao->countPhotos($category, $checkPrivacy);
    }
	
	 /**
     * Returns photo list 
     *
     * @param string $type
     * @param int $page
     * @param int $limit
     * @return array of PHOTO_BOL_Photo
     */
    public function findPhotoList( $type, $page, $limit, $checkPrivacy = true )
    {
        if ( $type == 'toprated' )
        {
            $first = ( $page - 1 ) * $limit;
            $topRatedList = BOL_RateService::getInstance()->findMostRatedEntityList('photo_rates', $first, $limit);

            if ( !$topRatedList )
            {
                return array();
            }
            $photoArr = $this->advancedphotoDao->findPhotoInfoListByIdList(array_keys($topRatedList));

            $photos = array();

            foreach ( $photoArr as $key => $photo )
            {
                $photos[$key] = $photo;
                $photos[$key]['score'] = $topRatedList[$photo['id']]['avgScore'];
                $photos[$key]['rates'] = $topRatedList[$photo['id']]['ratesCount'];
            }

            usort($photos, array('PHOTO_BOL_PhotoService', 'sortArrayItemByDesc'));
        }
        else
        {
            $photos = $this->advancedphotoDao->getPhotoList($type, $page, $limit, $checkPrivacy);
        }

        if ( $photos )
        {
            foreach ( $photos as $key => $photo )
            {
                $photos[$key]['url'] = $this->photoService->getPhotoPreviewUrl($photo['id']);
            }
        }

        return $photos;
    }
       /**
     * Counts photos
     *
     * @param string $type
     * @return int
     */
    public function countPhotosFeature( $type, $checkPrivacy = true )
    {
        if ( $type == 'toprated' )
        {
            return BOL_RateService::getInstance()->findMostRatedEntityCount('photo');
        }

        return $this->advancedphotoDao->countPhotosFeature($type, $checkPrivacy);
    }
}