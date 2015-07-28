<?php

class Vtiger_EdiImportResult_View extends Vtiger_Index_View {
	
	public function process(Vtiger_Request $request) {

		$module = $request->get('module');
		
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $module);
		$viewer->assign('IMPORT_REC', $request->get('ok_rec'));
		$viewer->assign('SKIP_REC', $request->get('fail'));
		$viewer->view('EdiImportResult.tpl', $module);
		
	}
}
