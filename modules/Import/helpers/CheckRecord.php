<?php

/* +***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 * *********************************************************************************************************************************** */

class CheckRecord {
	
	public function checkExistByEAN($ean) {
		
		$result = self::checkProductOrServiceExistByEAN($ean, 'Products');
		if (!$result) {
			$result = self::checkProductOrServiceExistByEAN($ean, 'Services');
		}
		
		return $result;
	}
	
	public static function checkProductOrServiceExistByEAN($ean, $module) {
		
		require_once "modules/$module/$module.php";
		
		$db = PearDatabase::getInstance();
		
		$focus = new $module();
		
		$sql = "SELECT {$focus->table_index} FROM {$focus->table_name} "
		. "INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = {$focus->table_name}.{$focus->table_index} "
		. "WHERE deleted = 0 AND ean = ?";
						
		$result = $db->pquery($sql, array($ean), true);
				
		return $db->query_result($result, 0, $focus->table_index);
	}
	
	public static function checkAccountByILN($iln) {
		$db = PearDatabase::getInstance();
		
		$ac = new Accounts();
		
		$sql = "SELECT {$ac->table_index} FROM {$ac->table_name} "
		. "INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = {$ac->table_name}.{$ac->table_index} "
		. "WHERE deleted = 0 AND iln = ?";
		
		$result = $db->pquery($sql, array($iln), true);
		
		return $db->query_result($result, 0, $ac->table_index);
	}
	
	public static function checkProductExistByInternalNo($no) {
				
		$db = PearDatabase::getInstance();
				
		$sql = "SELECT productid FROM vtiger_productcf "
		. "INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_productcf.productid "
		. "WHERE deleted = 0 AND cf_868 = ?";
						
		$result = $db->pquery($sql, array($no), true);
				
		return $db->query_result($result, 0, 'productid');
	}
}