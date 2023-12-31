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

print_aged_supplier_analysis();

//----------------------------------------------------------------------------------------------------

function get_invoices($supplier_id, $to, $all=true) {
	$todate = date2sql($to);
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;

	// Revomed allocated from sql
	if ($all)
		$value = "(trans.ov_amount + trans.ov_gst + trans.ov_discount)";
	else
		$value = "IF (trans.type=".ST_SUPPINVOICE." OR trans.type=".ST_BANKDEPOSIT.", 
		(trans.ov_amount + trans.ov_gst + trans.ov_discount - trans.alloc),
		(trans.ov_amount + trans.ov_gst + trans.ov_discount + trans.alloc))";
	$due = "IF (trans.type=".ST_SUPPINVOICE." OR trans.type=".ST_SUPPCREDIT.",trans.due_date,trans.tran_date)";
	$sql = "SELECT trans.type,
		trans.reference,
		trans.tran_date,
		$value as Balance,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) > 0,$value,0) AS Due,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) > $PastDueDays1,$value,0) AS Overdue1,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) > $PastDueDays2,$value,0) AS Overdue2

		FROM ".TB_PREF."suppliers supplier,
			".TB_PREF."supp_trans trans

		WHERE supplier.supplier_id = trans.supplier_id
			AND trans.supplier_id = $supplier_id
			AND trans.tran_date <= '$todate'
			AND ABS(trans.ov_amount + trans.ov_gst + trans.ov_discount) > ".FLOAT_COMP_DELTA;
	if (!$all)
		$sql .= " AND ABS(trans.ov_amount + trans.ov_gst + trans.ov_discount) - trans.alloc > ".FLOAT_COMP_DELTA;
	$sql .= " ORDER BY trans.tran_date";

	return db_query($sql, 'The supplier details could not be retrieved');
}

//----------------------------------------------------------------------------------------------------

function print_aged_supplier_analysis() {
	global $path_to_root, $systypes_array, $SysPrefs;

	$to = $_POST['PARAM_0'];
	$fromsupp = $_POST['PARAM_1'];
	$currency = $_POST['PARAM_2'];
	$show_all = $_POST['PARAM_3'];
	$summaryOnly = $_POST['PARAM_4'];
	$no_zeros = $_POST['PARAM_5'];
	$graphics = $_POST['PARAM_6'];
	$comments = $_POST['PARAM_7'];
	$orientation = $_POST['PARAM_8'];
	$destination = $_POST['PARAM_9'];

	if ($destination)
		include_once($path_to_root.'/reporting/includes/excel_report.inc');
	else
		include_once($path_to_root.'/reporting/includes/pdf_report.inc');
	$orientation = ($orientation ? 'L' : 'P');
	if ($graphics) {
		include_once($path_to_root.'/reporting/includes/class.graphic.inc');
		$pg = new graph();
	}

	$from = $fromsupp == ALL_TEXT ? _('All') : get_supplier_name($fromsupp);
	$dec = user_price_dec();
	$summary = $summaryOnly == 1 ? _('Summary Only') : _('Detailed Report');

	if ($currency == ALL_TEXT) {
		$convert = true;
		$currency = _('Balances in Home Currency');
	}
	else
		$convert = false;

	$nozeros = $no_zeros ? _('Yes') : _('No');
	$show = $show_all ? _('Yes') : _('No');

	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;
	$nowdue = '1-'.$PastDueDays1.' '._('Days');
	$pastdue1 = ($PastDueDays1 + 1).'-'.$PastDueDays2.' '._('Days');
	$pastdue2 = _('Over').' '.$PastDueDays2.' '._('Days');

	$cols = array(0, 100, 130, 190,	250, 320, 385, 450,	515);

	$headers = array(_('Supplier'),	'',	'',	_('Current'), $nowdue, $pastdue1,$pastdue2, _('Total Balance'));

	$aligns = array('left',	'left',	'left',	'right', 'right', 'right', 'right',	'right');

	$params = array(0 => $comments,
					1 => array('text' => _('End Date'), 'from' => $to, 'to' => ''),
					2 => array('text' => _('Supplier'), 'from' => $from, 'to' => ''),
					3 => array('text' => _('Currency'),'from' => $currency,'to' => ''),
					4 => array('text' => _('Type'), 'from' => $summary,'to' => ''),
					5 => array('text' => _('Show Also Allocated'), 'from' => $show, 'to' => ''),		
					6 => array('text' => _('Suppress Zeros'), 'from' => $nozeros, 'to' => ''));

	if ($convert)
		$headers[2] = _('currency');
	$rep = new FrontReport(_('Aged Supplier Analysis'), 'AgedSupplierAnalysis', user_pagesize(), 9, $orientation);
	if ($orientation == 'L')
		recalculate_cols($cols);

	$rep->Font();
	$rep->Info($params, $cols, $headers, $aligns);
	$rep->NewPage();

	$total = array();
	$total[0] = $total[1] = $total[2] = $total[3] = $total[4] = 0.0;
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;

	$nowdue = '1-'.$PastDueDays1.' '._('Days');
	$pastdue1 = ($PastDueDays1 + 1).'-'.$PastDueDays2.' '._('Days');
	$pastdue2 = _('Over').' '.$PastDueDays2.' '._('Days');

	$sql = "SELECT supplier_id, supp_name AS name, curr_code FROM ".TB_PREF."suppliers";
	if ($fromsupp != ALL_TEXT)
		$sql .= " WHERE supplier_id=".db_escape($fromsupp);
	$sql .= " ORDER BY supp_name";
	$result = db_query($sql, 'The suppliers could not be retrieved');

	while ($myrow=db_fetch($result)) {
		if (!$convert && $currency != $myrow['curr_code'])
			continue;

		if ($convert)
			$rate = get_exchange_rate_from_home_currency($myrow['curr_code'], $to);
		else
			$rate = 1.0;

		$supprec = get_supplier_details($myrow['supplier_id'], $to, $show_all);
		if (!$supprec)
			continue;
		$supprec['Balance'] *= $rate;
		$supprec['Due'] *= $rate;
		$supprec['Overdue1'] *= $rate;
		$supprec['Overdue2'] *= $rate;

		$str = array($supprec['Balance'] - $supprec['Due'],
			$supprec['Due']-$supprec['Overdue1'],
			$supprec['Overdue1']-$supprec['Overdue2'],
			$supprec['Overdue2'],
			$supprec['Balance']);

		if ($no_zeros && floatcmp(array_sum($str), 0) == 0)
			continue;

		$rep->Font('bold');
		$rep->TextCol(0, 2,	$myrow['name']);
		if ($convert) $rep->TextCol(2, 3,	$myrow['curr_code']);
		$rep->Font();
		$total[0] += ($supprec['Balance'] - $supprec['Due']);
		$total[1] += ($supprec['Due']-$supprec['Overdue1']);
		$total[2] += ($supprec['Overdue1']-$supprec['Overdue2']);
		$total[3] += $supprec['Overdue2'];
		$total[4] += $supprec['Balance'];
		for ($i = 0; $i < count($str); $i++)
			$rep->AmountCol($i + 3, $i + 4, $str[$i], $dec);
		$rep->NewLine(1, 2);
		if (!$summaryOnly) {
			$res = get_invoices($myrow['supplier_id'], $to, $show_all);
			if (db_num_rows($res)==0) {
				$rep->Line($rep->row + 4);
				$rep->NewLine(1, 2);
				continue;
			}
			
			while ($trans=db_fetch($res)) {
				$rep->NewLine(1, 2);
				$rep->TextCol(0, 1, $systypes_array[$trans['type']], -2);
				$rep->TextCol(1, 2,	$trans['reference'], -2);
				$rep->TextCol(2, 3,	sql2date($trans['tran_date']), -2);
				foreach ($trans as $i => $value)
					$trans[$i] = (float)$trans[$i] * $rate;
				$str = array($trans['Balance'] - $trans['Due'],
					$trans['Due']-$trans['Overdue1'],
					$trans['Overdue1']-$trans['Overdue2'],
					$trans['Overdue2'],
					$trans['Balance']);
				for ($i = 0; $i < count($str); $i++)
					$rep->AmountCol($i + 3, $i + 4, $str[$i], $dec);
			}
			$rep->Line($rep->row - 8);
			$rep->NewLine(2);
		}
	}
	if ($summaryOnly) {
		$rep->Line($rep->row  + 4);
		$rep->NewLine();
	}
	$rep->fontSize += 2;
	$rep->TextCol(0, 3,	_('Grand Total'));
	$rep->fontSize -= 2;
	for ($i = 0; $i < count($total); $i++) {
		$rep->AmountCol($i + 3, $i + 4, $total[$i], $dec);
		if ($graphics && $i < count($total) - 1)
			$pg->y[$i] = abs($total[$i]);
	}
	$rep->Line($rep->row  - 8);
	$rep->NewLine();
	if ($graphics) {
		$pg->x = array(_('Current'), $nowdue, $pastdue1, $pastdue2);
		$pg->title     = $rep->title;
		$pg->axis_x    = _('Days');
		$pg->axis_y    = _('Amount');
		$pg->graphic_1 = $to;
		$pg->type      = $graphics;
		$pg->skin      = $SysPrefs->graph_skin;
		$pg->built_in  = false;
		$pg->latin_notation = ($SysPrefs->decseps[user_dec_sep()] != '.');
		$filename = company_path().'/pdf_files/'. random_id().'.png';
		$pg->display($filename, true);
		$w = $pg->width / 1.5;
		$h = $pg->height / 1.5;
		$x = ($rep->pageWidth - $w) / 2;
		$rep->NewLine(2);
		if ($rep->row - $h < $rep->bottomMargin)
			$rep->NewPage();
		$rep->AddImage($filename, $x, $rep->row - $h, $w, $h);
	}
	$rep->End();
}
