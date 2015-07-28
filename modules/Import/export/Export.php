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

abstract class Export {

	public function getRecords(Vtiger_Request $request) {
		$db = PearDatabase::getInstance();
		$moduleName = $request->get('source_module');

		$this->moduleInstance = Vtiger_Module_Model::getInstance($moduleName);
		$this->moduleFieldInstances = $this->moduleInstance->getFields();
		$this->focus = CRMEntity::getInstance($moduleName);

		$query = $this->getExportQuery($request);
		$explodeQuery = explode("SELECT ", $query);
		$focus = new $moduleName();
		$query = "SELECT " . $focus->table_name . '.' . $focus->table_index . ' as id, ' . $explodeQuery[1];
		$result = $db->pquery($query, array());

		$existingRecord = false;

		$entries = array();
		for ($j = 0; $j < $db->num_rows($result); $j++) {
			$resultTab = $db->fetchByAssoc($result, $j);
			$resultTab['label'] = $resultTab[strtolower($moduleName).'_no'];
			// client wanted file names as part of records label text
			/*$resultTab['label'] = explode('-', $resultTab['label']);
			$resultTab['label'] = array_pop($resultTab['label']);*/
			if ($resultTab['id'] != $existingRecord) {
				$entries[] = $this->sanitizeValues($resultTab);
				$existingRecord = $resultTab['id'];
			}
		}

		return $entries;
	}

	function getExportQuery(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$mode = $request->getMode();
		$cvId = $request->get('viewname');
		$moduleName = $request->get('source_module');

		$queryGenerator = new QueryGenerator($moduleName, $currentUser);
		$queryGenerator->initForCustomViewById($cvId);
		$fieldInstances = $this->moduleFieldInstances;

		$accessiblePresenceValue = array(0, 2);
		foreach ($fieldInstances as $field) {
			// Check added as querygenerator is not checking this for admin users
			$presence = $field->get('presence');
			if (in_array($presence, $accessiblePresenceValue)) {
				$fields[] = $field->getName();
			}
		}
		$queryGenerator->setFields($fields);
		$query = $queryGenerator->getQuery();

		if (in_array($moduleName, getInventoryModules())) {
			$query = $this->moduleInstance->getExportQuery($this->focus, $query);
		}

		$this->accessibleFields = $queryGenerator->getFields();

		switch ($mode) {
			case 'ExportAllData' : return $query;
				break;

			case 'ExportCurrentPage' : $pagingModel = new Vtiger_Paging_Model();
				$limit = $pagingModel->getPageLimit();

				$currentPage = $request->get('page');
				if (empty($currentPage))
					$currentPage = 1;

				$currentPageStart = ($currentPage - 1) * $limit;
				if ($currentPageStart < 0)
					$currentPageStart = 0;
				$query .= ' LIMIT ' . $currentPageStart . ',' . $limit;

				return $query;
				break;

			case 'ExportSelectedRecords' : $idList = $this->getRecordsListFromRequest($request);
				$baseTable = $this->moduleInstance->get('basetable');
				$baseTableColumnId = $this->moduleInstance->get('basetableid');
				if (!empty($idList)) {
					if (!empty($baseTable) && !empty($baseTableColumnId)) {
						$idList = implode(',', $idList);
						$query .= ' AND ' . $baseTable . '.' . $baseTableColumnId . ' IN (' . $idList . ')';
					}
				} else {
					$query .= ' AND ' . $baseTable . '.' . $baseTableColumnId . ' NOT IN (' . implode(',', $request->get('excluded_ids')) . ')';
				}
				return $query;
				break;


			default : return $query;
				break;
		}
	}

	function sanitizeValues($arr) {
		$db = PearDatabase::getInstance();
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$roleid = $currentUser->get('roleid');
		if (empty($this->fieldArray)) {
			$this->fieldArray = $this->moduleFieldInstances;
			foreach ($this->fieldArray as $fieldName => $fieldObj) {
				//In database we have same column name in two tables. - inventory modules only
				if ($fieldObj->get('table') == 'vtiger_inventoryproductrel' && ($fieldName == 'discount_amount' || $fieldName == 'discount_percent')) {
					$fieldName = 'item_' . $fieldName;
					$this->fieldArray[$fieldName] = $fieldObj;
				} else {
					$columnName = $fieldObj->get('column');
					$this->fieldArray[$columnName] = $fieldObj;
				}
			}
		}
		$moduleName = $this->moduleInstance->getName();
		foreach ($arr as $fieldName => &$value) {
			if (isset($this->fieldArray[$fieldName])) {
				$fieldInfo = $this->fieldArray[$fieldName];
			} else {
				if ('id' != $fieldName) {
					unset($arr[$fieldName]);
				}
				continue;
			}
			$value = trim(decode_html($value), "\"");
			$uitype = $fieldInfo->get('uitype');
			$fieldname = $fieldInfo->get('name');

			if (!$this->fieldDataTypeCache[$fieldName]) {
				$this->fieldDataTypeCache[$fieldName] = $fieldInfo->getFieldDataType();
			}
			$type = $this->fieldDataTypeCache[$fieldName];

			if ($fieldname != 'hdnTaxType' && ($uitype == 15 || $uitype == 16 || $uitype == 33)) {
				if (empty($this->picklistValues[$fieldname])) {
					$this->picklistValues[$fieldname] = $this->fieldArray[$fieldname]->getPicklistValues();
				}
				// If the value being exported is accessible to current user
				// or the picklist is multiselect type.
				if ($uitype == 33 || $uitype == 16 || array_key_exists($value, $this->picklistValues[$fieldname])) {
					// NOTE: multipicklist (uitype=33) values will be concatenated with |# delim
					$value = trim($value);
				} else {
					$value = '';
				}
			} elseif ($uitype == 52 || $type == 'owner') {
				$value = Vtiger_Util_Helper::getOwnerName($value);
			} elseif ($type == 'reference') {
				$value = trim($value);
				if (!empty($value)) {
					$parent_module = getSalesEntityType($value);
					$displayValueArray = getEntityName($parent_module, $value);
					if (!empty($displayValueArray)) {
						foreach ($displayValueArray as $k => $v) {
							$displayValue = $v;
						}
					}
					if (!empty($parent_module) && !empty($displayValue)) {
						
					} else {
						$value = "";
					}
				} else {
					$value = '';
				}
			} elseif ($uitype == 72 || $uitype == 71) {
				$value = CurrencyField::convertToUserFormat($value, null, true, true);
			} elseif ($uitype == 7 && $fieldInfo->get('typeofdata') == 'N~O' || $uitype == 9) {
				$value = decimalFormat($value);
			} else if ($type == 'date' || $type == 'datetime') {
				$value = DateTimeField::convertToUserFormat($value);
			}
			if ($moduleName == 'Documents' && $fieldname == 'description') {
				$value = strip_tags($value);
				$value = str_replace('&nbsp;', '', $value);
				array_push($new_arr, $value);
			}
		}
		$arr['label'] = $arr[strtolower($moduleName).'_no'];
		$arr['label'] = explode('-', $arr['label']);
		$arr['label'] = array_pop($arr['label']);
		return $arr;
	}

	protected function getRecordsListFromRequest(Vtiger_Request $request) {
		$cvId = $request->get('viewname');
		$module = $request->get('module');
		if (!empty($cvId) && $cvId == "undefined") {
			$sourceModule = $request->get('sourceModule');
			$cvId = CustomView_Record_Model::getAllFilterByModule($sourceModule)->getId();
		}
		$selectedIds = $request->get('selected_ids');
		$excludedIds = $request->get('excluded_ids');

		if (!empty($selectedIds) && $selectedIds != 'all') {
			if (!empty($selectedIds) && count($selectedIds) > 0) {
				return $selectedIds;
			}
		}

		$customViewModel = CustomView_Record_Model::getInstanceById($cvId);
		if ($customViewModel) {
			$searchKey = $request->get('search_key');
			$searchValue = $request->get('search_value');
			$operator = $request->get('operator');
			if (!empty($operator)) {
				$customViewModel->set('operator', $operator);
				$customViewModel->set('search_key', $searchKey);
				$customViewModel->set('search_value', $searchValue);
			}

			$customViewModel->set('search_params', $request->get('search_params'));
			return $customViewModel->getRecordIds($excludedIds, $module);
		}
	}

	abstract function process(Vtiger_Request $request);
}
