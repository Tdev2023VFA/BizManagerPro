<?php
/************************************************************************
	Bản quyền (C) 2021 thuộc về Trần Anh Phương <http://aodieu.com>
	Chương trình được phát hành theo các điều khoản của giấy phép phần 
	mềm tự do GNU GPL được xuất bản bởi Quỹ Phần Mềm Tự Do (Free 
	Software Foundation), phiên bản thứ 3 của giấy phép, hoặc bất kỳ 
	phiên bản nào mới hơn (theo tùy chọn của bạn).
	Chương trình này được phân phối với kỳ vọng rằng nó sẽ có ích, nhưng 
	KHÔNG CÓ BẤT KỲ BẢO HÀNH NÀO, thậm chí KHÔNG CÓ BẢO ĐẢM NGỤ Ý VỀ KHẢ 
	NĂNG KHAI THÁC THƯƠNG MẠI HAY PHÙ HỢP VỚI MỤC ĐÍCH SỬ DỤNG CỤ THỂ NÀO.
	Chi tiết về giấy phép <http://www.gnu.org/licenses/gpl-3.0.html>.
*************************************************************************/
$path_to_root = '..';
include($path_to_root.'/includes/session.inc');
include_once($path_to_root.'/includes/types.inc'); // For tag constants
include_once($path_to_root.'/admin/db/tags_db.inc');
include($path_to_root.'/includes/ui.inc');

// Set up page security based on what type of tags we're working with
if (@$_GET['type'] == 'account' || get_post('type') == TAG_ACCOUNT)
	$page_security = 'SA_GLACCOUNTTAGS';
else if(@$_GET['type'] == "dimension" || get_post('type') == TAG_DIMENSION)
	$page_security = 'SA_DIMTAGS';

// We use $_POST['type'] throughout this script, so convert $_GET vars
// if $_POST['type'] is not set.
if (!isset($_POST['type'])) {
	if ($_GET['type'] == 'account')
		$_POST['type'] = TAG_ACCOUNT;
	elseif ($_GET['type'] == 'dimension')
		$_POST['type'] = TAG_DIMENSION;
	else
		die(_('Unspecified tag type'));
}

// Set up page based on what type of tags we're working with
switch ($_POST['type']) {
	case TAG_ACCOUNT:
		// Account tags
		$_SESSION['page_title'] = _($help_context = 'Account Tags');
		break;
	case TAG_DIMENSION:
		// Dimension tags
		$_SESSION['page_title'] = _($help_context = 'Dimension Tags');
}

page($_SESSION['page_title']);

simple_page_mode(true);

//-----------------------------------------------------------------------------------

function can_process() {
	if (strlen($_POST['name']) == 0) {
		display_error( _('The tag name cannot be empty.'));
		set_focus('name');
		return false;
	}
	return true;
}

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM') {
	if (can_process()) {
		if ($selected_id != -1) {
			if( $ret = update_tag($selected_id, $_POST['name'], $_POST['description']))
				display_notification(_('Selected tag settings have been updated'));
		} 
		else {
			if( $ret = add_tag($_POST['type'], $_POST['name'], $_POST['description']))
				display_notification(_('New tag has been added'));
		}
		if ($ret) $Mode = 'RESET';
	}
}

//-----------------------------------------------------------------------------------

function can_delete($selected_id) {
	if ($selected_id == -1)
		return false;

	$result = get_records_associated_with_tag($selected_id);
	
	if (db_num_rows($result) > 0) {
		display_error(_('Cannot delete this tag because records have been created referring to it.'));
		return false;
	}

	return true;
}

//-----------------------------------------------------------------------------------

if ($Mode == 'Delete') {
	if (can_delete($selected_id)) {
		delete_tag($selected_id);
		display_notification(_('Selected tag has been deleted'));
	}
	$Mode = 'RESET';
}

//-----------------------------------------------------------------------------------

if ($Mode == 'RESET') {
	$selected_id = -1;
	$_POST['name'] = $_POST['description'] = '';
}

//-----------------------------------------------------------------------------------

$result = get_tags($_POST['type'], check_value('show_inactive'));

start_form();
start_table(TABLESTYLE);
$th = array(_('Tag Name'), _('Tag Description'), '', '');
inactive_control_column($th);
table_header($th);

$k = 0;
while ($myrow = db_fetch($result)) {
	alt_table_row_color($k);

	label_cell($myrow['name']);
	label_cell($myrow['description']);
	inactive_control_cell($myrow['id'], $myrow['inactive'], 'tags', 'id');
	edit_button_cell('Edit'.$myrow['id'], _('Edit'));
	delete_button_cell('Delete'.$myrow['id'], _('Delete'));
	end_row();
}

inactive_control_row($th);
end_table(1);

//-----------------------------------------------------------------------------------

start_table(TABLESTYLE2);

if ($selected_id != -1) { // We've selected a tag 
	if ($Mode == 'Edit') {
		// Editing an existing tag
		$myrow = get_tag($selected_id);
		$_POST['name'] = $myrow['name'];
		$_POST['description'] = $myrow['description'];
	}
	// Note the selected tag
	hidden('selected_id', $selected_id);
}
	
text_row_ex(_('Tag Name:'), 'name', 15, 30);
text_row_ex(_('Tag Description:'), 'description', 40, 60);
hidden('type');

end_table(1);

submit_add_or_update_center($selected_id == -1, '', 'both');

end_form();
end_page();