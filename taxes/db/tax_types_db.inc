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
function add_tax_type($name, $sales_gl_code, $purchasing_gl_code, $rate) {
	$sql = "INSERT INTO ".TB_PREF."tax_types (name, sales_gl_code, purchasing_gl_code, rate)
		VALUES (".db_escape($name).", ".db_escape($sales_gl_code)
		.", ".db_escape($purchasing_gl_code).", $rate)";

	db_query($sql, 'could not add tax type');
}

function update_tax_type($type_id, $name, $sales_gl_code, $purchasing_gl_code, $rate) {
	$sql = "UPDATE ".TB_PREF."tax_types SET name=".db_escape($name).",
		sales_gl_code=".db_escape($sales_gl_code).",
		purchasing_gl_code=".db_escape($purchasing_gl_code).",
		rate=$rate
		WHERE id=".db_escape($type_id);

	db_query($sql, 'could not update tax type');
}

function get_all_tax_types($all=false) {
	$sql = "SELECT tax_type.*,
				Chart1.account_name AS SalesAccountName,
				Chart2.account_name AS PurchasingAccountName
			FROM ".TB_PREF."tax_types tax_type LEFT JOIN "
				.TB_PREF."chart_master AS Chart1 ON tax_type.sales_gl_code = Chart1.account_code LEFT JOIN "
				.TB_PREF."chart_master AS Chart2 ON tax_type.purchasing_gl_code = Chart2.account_code";

	if (!$all)
		$sql .= " AND !tax_type.inactive";
	return db_query($sql, 'could not get all tax types');
}

function get_all_tax_types_simple() {
	$sql = "SELECT * FROM ".TB_PREF."tax_types WHERE !inactive";

	return db_query($sql, 'could not get all tax types');
}

function get_tax_type($type_id) {
	$sql = "SELECT tax_type.*,
				Chart1.account_name AS SalesAccountName,
				Chart2.account_name AS PurchasingAccountName
			FROM ".TB_PREF."tax_types tax_type LEFT JOIN "
				.TB_PREF."chart_master AS Chart1 ON tax_type.sales_gl_code = Chart1.account_code LEFT JOIN "
				.TB_PREF."chart_master AS Chart2 ON tax_type.purchasing_gl_code = Chart2.account_code 
			WHERE id=".db_escape($type_id);

	$result = db_query($sql, 'could not get tax type');
	return db_fetch($result);
}

function get_tax_type_rate($type_id) {
	$sql = "SELECT rate FROM ".TB_PREF."tax_types WHERE id=".db_escape($type_id);

	$result = db_query($sql, 'could not get tax type rate');

	$row = db_fetch_row($result);
	return $row[0];
}

function delete_tax_type($type_id) {
	begin_transaction();

	$sql = "DELETE FROM ".TB_PREF."tax_types WHERE id=".db_escape($type_id);

	db_query($sql, "could not delete tax type");

	// also delete any item tax exemptions associated with this type
	$sql = "DELETE FROM ".TB_PREF."item_tax_type_exemptions WHERE tax_type_id=".db_escape($type_id);

	db_query($sql, 'could not delete item tax type exemptions');

	commit_transaction();
}

/*
	Check if gl_code is used by more than 2 tax types,
	or check if the two gl codes are not used by any other 
	than selected tax type.
	Necessary for pre-2.2 installations.
*/
function is_tax_gl_unique($gl_code, $gl_code2=-1, $selected_id=-1) {

	$purch_code = $gl_code2== -1 ? $gl_code : $gl_code2;

	$sql = "SELECT count(*) FROM "
			.TB_PREF."tax_types	
		WHERE (sales_gl_code=" .db_escape($gl_code)
			." OR purchasing_gl_code=" .db_escape($purch_code). ")";

	if ($selected_id != -1)
		$sql .= " AND id!=".db_escape($selected_id);

	$res = db_query($sql, 'could not query gl account uniqueness');
	$row = db_fetch($res);

	return $gl_code2 == -1 ? ($row[0] <= 1) : ($row[0] == 0);
}

function is_tax_account($account_code) {
	$sql = "SELECT id FROM ".TB_PREF."tax_types WHERE 
		sales_gl_code=".db_escape($account_code)." OR purchasing_gl_code=".db_escape($account_code);
	$result = db_query($sql, 'checking account is tax account');
	if (db_num_rows($result) > 0) {
		$acct = db_fetch($result);
		return $acct['id'];
	}
	
	return false;
}
