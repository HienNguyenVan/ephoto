<?php

class ADVANCEDPHOTO_CMP_FeaturedPhotos extends OW_Component
{
   
    public function __construct( array $params)
    {

        parent::__construct();
       	$this->assign('listType', 'featured');
		OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('advancedphoto')->getStaticJsUrl() . 'hapsp.min.js');
		$script = '
			hapsp.initialize({request_url: "", max_width: 150, id:"#hapFeaturedPhotos", loading_on_scroll:false});			  
		';
		OW::getDocument()->addOnloadScript($script);
		
		if(OW::getPluginManager()->isPluginActive('gphotoviewer')){
			$script = "PhotoViewer.bindPhotoViewer();";
			OW::getDocument()->addOnloadScript($script);
		}else{			
			/*OW::getLanguage()->addKeyForJs('photo', 'tb_edit_photo');
			OW::getLanguage()->addKeyForJs('photo', 'confirm_delete');
			OW::getLanguage()->addKeyForJs('photo', 'mark_featured');
			OW::getLanguage()->addKeyForJs('photo', 'remove_from_featured');
			
			$objParams = array(
				'ajaxResponder' => OW::getRouter()->urlFor('PHOTO_CTRL_Photo', 'ajaxResponder'),
				'fbResponder' => OW::getRouter()->urlForRoute('photo.floatbox')
			);

			$script = '$("div.photo a").on("click", function(e){
				e.preventDefault();
				var photo_id = $(this).attr("rel");

				if ( !window.photoViewObj ) {
					window.photoViewObj = new photoView('.json_encode($objParams).');
				}
				
				window.photoViewObj.setId(photo_id);
			}); ';
			OW::getDocument()->addOnloadScript($script);*/
		}
    }
}