<?php
class BackupAdmin extends LeftAndMain {
	private static $url_segment = 'backups';
	private static $url_rule = '/$Action/$ID/$OtherID';
	private static $menu_title = 'Backups';
	private static $tree_class = 'BackupAdminSettings';
	//private static $menu_icon = 'custom-admin/images/custom.png';
	private static $priority = -1;

	public function init() {
		parent::init();
		// Gather required client side resources
		Requirements::javascript(CMS_DIR . '/javascript/CMSMain.EditForm.js');
	}
	/**
	 * Get the response negotiator and ste the from template in the callback
	 * @return PjaxResponseNegotiator
	 */
	public function getResponseNegotiator() {
		// Get the reponse negotiator
		$negotiator = parent::getResponseNegotiator();
		$controller = $this;
		// Set the callback template
		$negotiator->setCallback('CurrentForm', function() use(&$controller) {
			return $controller->renderWith($controller->getTemplatesWithSuffix('_Content'));
		});
		
		return $negotiator;
	}
	public function getEditForm($id = null, $fields = null) {
		$backupAdminSettings = BackupAdminSettings::current_config();
		// Get the from fields
		$fields = $backupAdminSettings->getCMSFields();
	

		// Get the form actions
		$actions = $backupAdminSettings->getCMSActions();
		// Create the form
		$form = CMSForm::create($this, 'EditForm', $fields, $actions)->setHTMLID('Form_EditForm');
		// Set the response action, mostly for returning the correst template
		$form->setResponseNegotiator($this->getResponseNegotiator());
		// Add required classes to the form
		$form->addExtraClass('cms-content center cms-edit-form');
		// Set the from tmeplate 
		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
		// Ensure fields have a 'Root' tab
		if($form->Fields()->hasTabset()) {
			$form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
		}
		// Load the data from the CustomAdminSettings DataObject 
		$form->loadDataFrom($backupAdminSettings);
		// Convert buttons to button tags (apprently required for jQuery styling)
		$actions = $actions->dataFields();
		if($actions) {
			foreach($actions as $action) {
				$action->setUseButtonTag(true);	
			}
		}
		return $form;
	}
	/**
	 * Save the settings
	 * @param  array $data The form data
	 * @param  CMSForm $form The form object
	 * @return SS_HTTPResponse The SilverStripe viewresponse
	 */
	public function save_settings($data, $form) {
		// Get the current CustomAdminSettings object
		$backupAdminSettings = BackupAdminSettings::current_config();
		
		// Save the data into the current CustomAdminSetting Object
		$form->saveInto($backupAdminSettings);
		
		// Write Object to the database 
		try {
			$backupAdminSettings->write();
		} catch(ValidationException $ex) {
			$form->sessionMessage($ex->getResult()->message(), 'bad');
			return $this->getResponseNegotiator()->respond($this->request);
		}
		
		// Add response headers for CMS 
		$this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.SAVEDUP', 'Saved.')));
		return $this->getResponseNegotiator()->respond($this->request);
	}
}
