<?php
class EPHOTO_BOL_CategoryService {

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
        return EPHOTO_BOL_CategoryDao::getInstance()->findAll();
    }

    public function addCategory($name, $description) {
        if (EPHOTO_BOL_CategoryDao::getInstance()->isDuplicate($name)) {
            return false;
        } else {
            $category = new EPHOTO_BOL_Category();
            $category->name = $name;
            $category->description = $description;
            EPHOTO_BOL_CategoryDao::getInstance()->save($category);
            return $category->id;
        }
    }

    public function deleteCategory($id) {
        EPHOTO_BOL_CategoryDao::getInstance()->deleteById($id);
    }

    public function isDuplicate($category) {
        return EPHOTO_BOL_CategoryDao::getInstance()->isDuplicate($category);
    }

    public function getCategoryName($id) {
        return EPHOTO_BOL_CategoryDao::getInstance()->findById($id)->name;
    }

    public function getCategoryId($category) {
        return EPHOTO_BOL_CategoryDao::getInstance()->getCategoryId($category);
    }

}

