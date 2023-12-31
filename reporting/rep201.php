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
$page_security = 'SA_SUPPLIERANALYTIC';
$path_to_root = '..';

include_once($path_to_root.'/includes/session.inc');
include_once($path_to_root.'/includes/date_functions.inc');
include_once($path_to_root.'/includes/data_checks.inc');
include_once($path_to_root.'/gl/includes/gl_db.inc');

//----------------------------------------------------------------------------------------------------

print_supplier_balances();

function get_open_balance($supplier_id, $to) {
	if ($to)
		$to = date2sql($to);

	$sql = "SELECT SUM(IF(t.type = ".ST_SUPPINVOICE." OR (t.type IN (".ST_JOURNAL." , ".ST_BANKDEPOSIT.") AND t.ov_amount>0),
		-abs(t.ov_amount + t.ov_gst + t.ov_discount), 0)) AS charges,";

	$sql .= "SUM(IF(t.type != ".ST_SUPPINVOICE." AND NOT(t.type IN (".ST_JOURNAL." , ".ST_BANKDEPOSIT.") AND t.ov_amount>0),
		abs(t.ov_amount + t.ov_gst + t.ov_discount) * -1, 0)) AS credits,";

	$sql .= "SUM(IF(t.type != ".ST_SUPPINVOICE." AND NOT(t.type IN (".ST_JOURNAL." , ".ST_BANKDEPOSIT.")), t.alloc * -1, t.alloc)) 
		AS Allocated,";

	$sql .= "SUM(IF(t.type = ".ST_SUPPINVOICE.", 1, -1) *
		(abs(t.ov_amount + t.ov_gst + t.ov_discount) - abs(t.alloc))) AS OutStanding
		FROM ".TB_PREF."supp_trans t
		WHERE t.supplier_id = ".db_escape($supplier_id);
	if ($to)
		$sql .= " AND t.tran_date < '$to'";
	$sql .= " GROUP BY supplier_id";

	$result = db_query($sql, 'No transactions were returned');
	return db_fetch($result);
}

function getTransactions($supplier_id, $from, $to) {
	$sql = "SELECT *,
				(ov_amount + ov_gst + ov_discount) AS TotalAmount,
				alloc AS Allocated,
				((type = ".ST_SUPPINVOICE.") AND due_date < '$to') AS OverDue
			FROM ".TB_PREF."supp_trans
			WHERE tran_date >= '".date2sql($from)."' AND tran_date <= '".date2sql($to)."' 
				AND supplier_id = '$supplier_id' AND ov_amount != 0
				ORDER BY tran_date";

	$result = db_query($sql, 'No transactions were returned');

	return $result;
}

//----------------------------------------------------------------------------------------------------

function print_supplier_balances() {
	global $path_to_root, $systypes_array;

	$from = $_POST['PARAM_0'];
	$to = $_POST['PARAM_1'];
	$fromsupp = $_POST['PARAM_2'];
	$show_balance = $_POST['PARAM_3'];
	$currency = $_POST['PARAM_4'];
	$no_zeros = $_POST['PARAM_5'];
	$comments = $_POST['PARAM_6'];
	$orientation = $_POST['PARAM_7'];
	$destination = $_POST['PARAM_8'];
	if ($destination)
		include_once($path_to_root.'/reporting/includes/excel_report.inc');
	else
		include_once($path_to_root.'/reporting/includes/pdf_report.inc');

	$orientation = ($orientation ? 'L' : 'P');
	$supp = $fromsupp == ALL_TEXT ? _('All') : get_supplier_name($fromsupp);
	$dec = user_price_dec();

	if ($currency == ALL_TEXT) {
		$convert = true;
		$currency = _('Balances in Home currency');
	}
	else
		$convert = false;

	$nozeros = $no_zeros ? _('Yes') : _('No');

	$cols = array(0, 95, 140, 200,	250, 320, 385, 450,	515);

	$headers = array(_('Trans Type'), _('#'), _('Date'), _('Due Date'), _('Charges'), _('Credits'), _('Allocated'), _('Outstanding'));

	if ($show_balance)
		$headers[7] = _('Balance');
	$aligns = array('left',	'left',	'left',	'left',	'right', 'right', 'right', 'right');

	$params = array(0 => $comments,
					1 => array('text' => _('Period'), 'from' => $from, 'to' => $to),
					2 => array('text' => _('Supplier'), 'from' => $supp, 'to' => ''),
					3 => array(  'text' => _('Currency'),'from' => $currency, 'to' => ''),
					4 => array('text' => _('Suppress Zeros'), 'from' => $nozeros, 'to' => ''));

	$rep = new FrontReport(_('Supplier Balances'), 'SupplierBalances', user_pagesize(), 9, $orientation);
	if ($orientation == 'L')
		recalculate_cols($cols);

	$rep->Font();
	$rep->Info($params, $cols, $headers, $aligns);
	$rep->NewPage();

	$total = array();
	$grandtotal = array(0, 0, 0, 0);

	$sql = "SELECT supplier_id, supp_name AS name, curr_code FROM ".TB_PREF."suppliers";
	if ($fromsupp != ALL_TEXT)
		$sql .= " WHERE supplier_id=".db_escape($fromsupp);
	$sql .= " ORDER BY supp_name";
	$result = db_query($sql, 'The customers could not be retrieved');

	while ($myrow=db_fetch($result)) {
		if (!$convert && $currency != $myrow['curr_code'])
			continue;
		$accumulate = 0;
		$rate = $convert ? get_exchange_rate_from_home_currency($myrow['curr_code'], Today()) : 1;
		$bal = get_open_balance($myrow['supplier_id'], $from);
		$init = array();
		$bal['charges'] = isset($bal['charges']) ? $bal['charges'] : 0;
		$bal['credits'] = isset($bal['credits']) ? $bal['credits'] : 0;
		$bal['Allocated'] = isset($bal['Allocated']) ? $bal['Allocated'] : 0;
		$bal['OutStanding'] = isset($bal['OutStanding']) ? $bal['OutStanding'] : 0;
		$init[0] = round2(abs($bal['charges']*$rate), $dec);
		$init[1] = round2(Abs($bal['credits']*$rate), $dec);
		$init[2] = round2($bal['Allocated']*$rate, $dec);
		if ($show_balance) {
			$init[3] = $init[0] - $init[1];
			$accumulate += $init[3];
		}	
		else	
			$init[3] = round2($bal['OutStanding']*$rate, $dec);
		$res = getTransactions($myrow['supplier_id'], $from, $to);
		if ($no_zeros && db_num_rows($res) == 0)
			continue;

		$rep->Font('bold');
		$rep->TextCol(0, 2, $myrow['name']);
		if ($convert)
			$rep->TextCol(2, 3,	$myrow['curr_code']);
		$rep->Font();
		$rep->TextCol(3, 4,	_('Open Balance'));
		$rep->AmountCol(4, 5, $init[0], $dec);
		$rep->AmountCol(5, 6, $init[1], $dec);
		$rep->AmountCol(6, 7, $init[2], $dec);
		$rep->AmountCol(7, 8, $init[3], $dec);
		$total = array(0,0,0,0);
		for ($i = 0; $i < 4; $i++) {
			$total[$i] += $init[$i];
			$grandtotal[$i] += $init[$i];
		}
		$rep->NewLine(1, 2);
		
		if (db_num_rows($res)==0) {
			$rep->Line($rep->row + 4);
			$rep->NewLine(1, 2);
			continue;
		}	
		while ($trans=db_fetch($res)) {
			if ($no_zeros && floatcmp(abs($trans['TotalAmount']), $trans['Allocated']) == 0)
				continue;
			$rep->NewLine(1, 2);
			$rep->TextCol(0, 1, $systypes_array[$trans['type']]);
			$rep->TextCol(1, 2,	$trans['reference']);
			$rep->DateCol(2, 3,	$trans['tran_date'], true);
			if ($trans['type'] == ST_SUPPINVOICE)
				$rep->DateCol(3, 4,	$trans['due_date'], true);
			$item[0] = $item[1] = 0.0;
			if ($trans['TotalAmount'] > 0.0) {
				$item[0] = round2(abs($trans['TotalAmount']) * $rate, $dec);
				$rep->AmountCol(4, 5, $item[0], $dec);
				$accumulate += $item[0];
				$item[2] = round2($trans['Allocated'] * $rate, $dec);
			}
			else {
				$item[1] = round2(abs($trans['TotalAmount']) * $rate, $dec);
				$rep->AmountCol(5, 6, $item[1], $dec);
				$accumulate -= $item[1];
				$item[2] = round2($trans['Allocated'] * $rate, $dec) * -1;
			}
			$rep->AmountCol(6, 7, $item[2], $dec);
			if ($trans['TotalAmount'] > 0.0)
				$item[3] = $item[0] - $item[2];
			else	
				$item[3] = -$item[1] - $item[2];
			if ($show_balance)	
				$rep->AmountCol(7, 8, $accumulate, $dec);
			else	
				$rep->AmountCol(7, 8, $item[3], $dec);
			for ($i = 0; $i < 4; $i++) {
				$total[$i] += $item[$i];
				$grandtotal[$i] += $item[$i];
			}
			if ($show_balance)
				$total[3] = $total[0] - $total[1];
		}
		
		$rep->NewLine(2);
		$rep->SetTextColor(205, 0, 30);
		$rep->TextCol(0, 3,	_('Total').' ('.$myrow['name'].')');
		for ($i = 0; $i < 4; $i++) {
			$rep->AmountCol($i + 4, $i + 5, $total[$i], $dec);
			$total[$i] = 0.0;
		}
		$rep->SetTextColor(0, 0, 0);
		$rep->Line($rep->row  - 4);
		$rep->NewLine(2);
	}
	$rep->fontSize += 2;
	$rep->TextCol(0, 3,	_('Grand Total'));
	$rep->fontSize -= 2;
	if ($show_balance)
		$grandtotal[3] = $grandtotal[0] - $grandtotal[1];
	for ($i = 0; $i < 4; $i++)
		$rep->AmountCol($i + 4, $i + 5,$grandtotal[$i], $dec);
	$rep->Line($rep->row  - 4);
	$rep->NewLine();
	$rep->End();
}
