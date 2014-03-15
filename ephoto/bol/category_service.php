<?php
class ADVANCEDPHOTO_BOL_CategoryService {

    private static $classInstance;

    public static function getInstance() {
        if (self::$classInstance === null) {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    private function __construct() {
        
    }

    public function getCategoriesList() {
        return ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->findAll();
    }

    public function addCategory($name, $description) {
        if (ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->isDuplicate($name)) {
            return false;
        } else {
            $category = new ADVANCEDPHOTO_BOL_Category();
            $category->name = $name;
            $category->description = $description;
            ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->save($category);
            return $category->id;
        }
    }

    public function deleteCategory($id) {
        ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->deleteById($id);
    }

    public function isDuplicate($category) {
        return ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->isDuplicate($category);
    }

    public function getCategoryName($id) {
        return ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->findById($id)->name;
    }

    public function getCategoryId($category) {
        return ADVANCEDPHOTO_BOL_CategoryDao::getInstance()->getCategoryId($category);
    }

}

