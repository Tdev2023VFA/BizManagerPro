<?php

if (!isset($path_to_root) || isset($_GET['path_to_root']) || isset($_POST['path_to_root']))
	die('Restricted access');

include_once($path_to_root.'/applications/application.php');
include_once($path_to_root.'/applications/customers.php');
include_once($path_to_root.'/applications/suppliers.php');
include_once($path_to_root.'/applications/inventory.php');
include_once($path_to_root.'/applications/fixed_assets.php');
include_once($path_to_root.'/applications/manufacturing.php');
include_once($path_to_root.'/applications/dimensions.php');
include_once($path_to_root.'/applications/generalledger.php');
include_once($path_to_root.'/applications/setup.php');
include_once($path_to_root.'/installed_extensions.php');

class QuanlyViet {
	var $user;
	var $settings;
	var $applications;
	var $selected_application;

	var $menu;

	function add_application($app) {	
		if ($app->enabled) // skip inactive modules
			$this->applications[$app->id] = $app;
	}
	function get_application($id) {
		if (isset($this->applications[$id]))
			return $this->applications[$id];
		return null;
	}
	function get_selected_application() {
		if (isset($this->selected_application))
			return $this->applications[$this->selected_application];
		foreach ($this->applications as $application)
			return $application;
		return null;
	}
	function display() {
		global $path_to_root;
			
		include_once($path_to_root.'/themes/'.user_theme().'/renderer.php');

		$this->init();
		$rend = new renderer();
		$rend->wa_header();

		$rend->display_applications($this);

		$rend->wa_footer();
		$this->renderer =& $rend;
	}
	function init() {
		global $SysPrefs;

		$this->menu = new menu(_('Main Menu'));
		$this->menu->add_item(_('Main Menu'), 'index.php');
		$this->menu->add_item(_('Logout'), '/account/access/logout.php');
		$this->applications = array();
		$this->add_application(new CustomersApp());
		$this->add_application(new SuppliersApp());
		$this->add_application(new InventoryApp());
		if ($SysPrefs->prefs['use_manufacturing'])
			$this->add_application(new ManufacturingApp());
		if ($SysPrefs->prefs['use_fixed_assets'])
			$this->add_application(new AssetsApp());
		$this->add_application(new DimensionsApp());
		$this->add_application(new GeneralLedgerApp());

		hook_invoke_all('install_tabs', $this);

		$this->add_application(new SetupApp());
	}
}
