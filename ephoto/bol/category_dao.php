<?php

class ADVANCEDPHOTO_BOL_CategoryDao extends OW_BaseDao {

    protected function __construct() {
        parent::__construct();
    }

    private static $classInstance;

    public static function getInstance() {
        if (self::$classInstance === null) {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function getDtoClassName() {
        return 'ADVANCEDPHOTO_BOL_Category';
    }

    public function getTableName() {
        return OW_DB_PREFIX . 'photo_categories';
    }

    public function getCategoryId($category) {
        $example = new OW_Example();
        $example->andFieldEqual('name', $category);
        $catObject = $this->findObjectByExample($example);

        if (count($catObject) > 0)
            return $catObject->id;
        else
            return false;
    }

    public function isDuplicate($category) {
        $example = new OW_Example();
        $example->andFieldEqual('name', $category);

        if (count($this->findObjectByExample($example)) > 0)
            return true;
        else
            return false;
    }

}