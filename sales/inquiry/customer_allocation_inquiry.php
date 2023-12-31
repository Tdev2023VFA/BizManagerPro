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
$page_security = 'SA_SALESALLOC';
$path_to_root = '../..';
include($path_to_root.'/includes/db_pager.inc');
include_once($path_to_root.'/includes/session.inc');

include_once($path_to_root.'/sales/includes/sales_ui.inc');
include_once($path_to_root.'/sales/includes/sales_db.inc');

$js = '';
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();
page(_($help_context = 'Customer Allocation Inquiry'), false, false, '', $js);

if (isset($_GET['customer_id']))
	$_POST['customer_id'] = $_GET['customer_id'];

//------------------------------------------------------------------------------------------------

if (!isset($_POST['customer_id']))
	$_POST['customer_id'] = get_global_customer();

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();

customer_list_cells(_('Select a customer: '), 'customer_id', $_POST['customer_id'], true);

date_cells(_('from:'), 'TransAfterDate', '', null, -user_transaction_days());
date_cells(_('to:'), 'TransToDate', '', null, 1);

cust_allocations_list_cells(_('Type:'), 'filterType', null);

check_cells(' '._('show settled:'), 'showSettled', null);

submit_cells('RefreshInquiry', _('Search'), '', _('Refresh Inquiry'), 'default');

set_global_customer($_POST['customer_id']);

end_row();
end_table();

//------------------------------------------------------------------------------------------------

function check_overdue($row) {
	return ($row['OverDue'] == 1 && (abs($row['TotalAmount']) - $row['Allocated'] != 0));
}

function order_link($row) {
	return $row['order_']>0 ? get_customer_trans_view_str(ST_SALESORDER, $row['order_']) : '';
}

function systype_name($dummy, $type) {
	global $systypes_array;

	return $systypes_array[$type];
}

function view_link($trans) {
	return get_trans_view_str($trans['type'], $trans['trans_no']);
}

function due_date($row) {
	return $row['type'] == ST_SALESINVOICE ? $row['due_date'] : '';
}

function fmt_balance($row) {
	return ($row['type'] == ST_JOURNAL && $row['TotalAmount'] < 0 ? -$row['TotalAmount'] : $row['TotalAmount']) - $row['Allocated'];
}

function alloc_link($row) {
	$link = 
	pager_link(_('Allocation'), '/sales/allocations/customer_allocate.php?trans_no='.$row['trans_no'].'&trans_type='.$row['type'].'&debtor_no='.$row['debtor_no'], ICON_ALLOC);

	if ($row['type'] == ST_CUSTCREDIT && $row['TotalAmount'] > 0)
		//its a credit note which could have an allocation
		return $link;
	else if ($row['type'] == ST_JOURNAL && $row['TotalAmount'] < 0)
		return $link;
	else if (($row['type'] == ST_CUSTPAYMENT || $row['type'] == ST_BANKDEPOSIT) && (floatcmp($row['TotalAmount'], $row['Allocated']) >= 0))
		//its a receipt  which could have an allocation
		return $link;
	elseif ($row['type'] == ST_CUSTPAYMENT && $row['TotalAmount'] <= 0)
		//its a negative receipt
		return '';
	elseif (($row['type'] == ST_SALESINVOICE && ($row['TotalAmount'] - $row['Allocated']) > 0) || 
		($row['type'] == ST_JOURNAL && (ABS($row['TotalAmount']) - $row['Allocated']) > 0) || $row['type'] == ST_BANKPAYMENT)
		return pager_link(_('Payment'), '/sales/customer_payments.php?customer_id='.$row['debtor_no'].'&SInvoice='.$row['trans_no'].'&Type='.$row['type'], ICON_MONEY);
}

function fmt_debit($row) {
	$value = $row['type']==ST_CUSTCREDIT || $row['type']==ST_CUSTPAYMENT || $row['type']==ST_BANKDEPOSIT ? -$row['TotalAmount'] : $row['TotalAmount'];
	return $value >= 0 ? price_format($value) : '';
}

function fmt_credit($row) {
	$value = !($row['type']==ST_CUSTCREDIT || $row['type']==ST_CUSTPAYMENT || $row['type']==ST_BANKDEPOSIT) ? -$row['TotalAmount'] : $row['TotalAmount'];
	return $value > 0 ? price_format($value) : '';
}

//------------------------------------------------------------------------------------------------

$sql = get_sql_for_customer_allocation_inquiry(get_post('TransAfterDate'), get_post('TransToDate'), get_post('customer_id'), get_post('filterType'), check_value('showSettled'));

//------------------------------------------------------------------------------------------------

$cols = array(
	_('Type') => array('fun'=>'systype_name'),
	_('#') => array('fun'=>'view_link', 'align'=>'right'),
	_('Reference'), 
	_('Order') => array('fun'=>'order_link', 'ord'=>'', 'align'=>'right'), 
	_('Date') => array('name'=>'tran_date', 'type'=>'date', 'ord'=>'asc'),
	_('Due Date') => array('type'=>'date', 'fun'=>'due_date'),
	_('Customer') => array('name' =>'name',  'ord'=>'asc'), 
	_('Currency') => array('align'=>'center'),
	_('Debit') => array('align'=>'right','fun'=>'fmt_debit'), 
	_('Credit') => array('align'=>'right','insert'=>true, 'fun'=>'fmt_credit'), 
	_('Allocated') => 'amount', 
	_('Balance') => array('type'=>'amount', 'insert'=>true, 'fun'=>'fmt_balance'),
	array('insert'=>true, 'fun'=>'alloc_link')
	);

if ($_POST['customer_id'] != ALL_TEXT) {
	$cols[_('Customer')] = 'skip';
	$cols[_('Currency')] = 'skip';
}

$table =& new_db_pager('doc_tbl', $sql, $cols);
$table->set_marker('check_overdue', _('Marked items are overdue.'));

$table->width = '80%';

display_db_pager($table);

end_form();
end_page();
