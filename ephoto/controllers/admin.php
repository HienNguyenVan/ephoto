<?php

class ADVANCEDPHOTO_CTRL_Admin extends ADMIN_CTRL_Abstract {

    public function __construct() {
        parent::__construct();

        if (OW::getRequest()->isAjax()) {
            return;
        }

        $language = OW::getLanguage();

        $menu = new BASE_CMP_ContentMenu();

        $menuItem = new BASE_MenuItem();
        $menuItem->setKey('admin-index');
        $menuItem->setLabel($language->text('advancedphoto', 'admin_tab_general_title'));
        $menuItem->setUrl(OW::getRouter()->urlForRoute('advancedphoto_admin_config'));
        $menuItem->setIconClass('ow_ic_files');
        $menuItem->setOrder(1);
        $menu->addElement($menuItem);

        $menuItem = new BASE_MenuItem();
        $menuItem->setKey('categories');
        $menuItem->setLabel($language->text('advancedphoto', 'admin_category_tab_title'));
        $menuItem->setUrl(OW::getRouter()->urlForRoute('advancedphoto_categories'));
        $menuItem->setIconClass('ow_ic_gear_wheel');
        $menuItem->setOrder(2);
        $menu->addElement($menuItem);


        $this->addComponent('menu', $menu);
        $this->menu = $menu;

        $this->setPageHeading(OW::getLanguage()->text('advancedphoto', 'admin_settings_title'));
        $this->setPageTitle(OW::getLanguage()->text('advancedphoto', 'admin_settings_title'));
        $this->setPageHeadingIconClass('ow_ic_gear_wheel');
    }

    public function index() {
        $language = OW::getLanguage();
        $config = OW::getConfig();

        $adminForm = new Form('adminForm');

        $element = new TextField('photofeature_per_page');
        $element->setRequired(true);
        $element->setLabel($language->text('advancedphoto', 'admin_photofeature_per_page'));
        $element->setDescription($language->text('advancedphoto', 'admin_photofeature_per_page_desc'));
        $element->setValue($config->getValue('advancedphoto', 'photofeature_per_page'));
        $adminForm->addElement($element);

        $element = new Submit('saveSettings');
        $element->setValue(OW::getLanguage()->text('photo', 'btn_edit'));
        $adminForm->addElement($element);

        if (OW::getRequest()->isPost()) {
                $values = $adminForm->getValues();
            if ($adminForm->isValid($_POST)) {
                $config->saveConfig('advancedphoto', 'photofeature_per_page', $_POST['photofeature_per_page']);
                OW::getFeedback()->info($language->text('advancedphoto', 'user_save_success'));
            }
        }

        $this->addForm($adminForm);
    }

    public function categories() {
        $adminForm = new Form('categoriesForm');

        $language = OW::getLanguage();

        $element = new TextField('categoryName');
        $element->setRequired();
        $element->setInvitation($language->text('advancedphoto', 'admin_category_name'));
        $element->setHasInvitation(true);
        $adminForm->addElement($element);

        $element = new Submit('addCategory');
        $element->setValue($language->text('advancedphoto', 'admin_add_category'));
        $adminForm->addElement($element);

        if (OW::getRequest()->isPost()) {
            if ($adminForm->isValid($_POST)) {
                $values = $adminForm->getValues();
                $name = ucwords(strtolower($values['categoryName']));
                $desc = ucwords(strtolower($values['categoryDesc']));
                if (ADVANCEDPHOTO_BOL_CategoryService::getInstance()->addCategory($name, $desc))
                    OW::getFeedback()->info($language->text('advancedphoto', 'admin_add_category_success'));
                else
                    OW::getFeedback()->error($language->text('advancedphoto', 'admin_add_category_error'));

                $this->redirect();
            }
        }

        $this->addForm($adminForm);

        $allCategories = array();
        $deleteUrls = array();

        $categories = ADVANCEDPHOTO_BOL_CategoryService::getInstance()->getCategoriesList();

        foreach ($categories as $category) {
            $allCategories[$category->id]['id'] = $category->id;
            $allCategories[$category->id]['name'] = $category->name;
            $deleteUrls[$category->id] = OW::getRouter()->urlFor(__CLASS__, 'delete', array('id' => $category->id));
        }

        $this->assign('allCategories', $allCategories);
        $this->assign('deleteUrls', $deleteUrls);
    }

    public function delete($params) {
        if (isset($params['id'])) {
            ADVANCEDPHOTO_BOL_CategoryService::getInstance()->deleteCategory((int) $params['id']);
        }

        $this->redirect(OW::getRouter()->urlForRoute('advancedphoto_categories'));
    }

	public function uninstall()
    {
        if ( isset($_POST['action']) && $_POST['action'] == 'delete_content' )
        {
            //OW::getConfig()->saveConfig('advancedphoto', 'uninstall_inprogress', 1);
            
            //PHOTO_BOL_PhotoService::getInstance()->setMaintenanceMode(true);
            OW::getDbo()->query("DROP TABLE IF EXISTS `" . OW_DB_PREFIX . "photo_categories`;");
			OW::getDbo()->query("ALTER TABLE `" . OW_DB_PREFIX . "photo_album` DROP `category_id`");
			
			BOL_PluginService::getInstance()->uninstall('advancedphoto');
			
            OW::getFeedback()->info(OW::getLanguage()->text('admin', 'manage_plugins_uninstall_success_message', array( 'plugin' => 'Advanced Photo' )));

			$this->redirect(OW::getRouter()->urlFor('ADMIN_CTRL_Plugins', 'index'));
        }
              
        $this->setPageHeading('Uninstall Advanced photo plugin');
        $this->setPageHeadingIconClass('ow_ic_delete');
        
        $this->assign('inprogress', (bool) OW::getConfig()->getValue('advancedphoto', 'uninstall_inprogress'));
        
        $js = new UTIL_JsGenerator();
        //$js->jQueryEvent('#btn-delete-content', 'click', 'if ( !confirm("Are you sure you want to uninstall \'Advanced Photo\' plugin?") ) return false;');
        
        //OW::getDocument()->addOnloadScript($js);
    }
}