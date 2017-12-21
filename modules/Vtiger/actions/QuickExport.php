<?php
/* {[The file is published on the basis of YetiForce Public License that can be found in the following directory: licenses/License.html]} */
require_once('libraries/PHPExcel/PHPExcel.php');

class MyReadFilter implements PHPExcel_Reader_IReadFilter
{
	public function readCell($column, $row, $worksheetName = '') {
		// Read title row and rows 20 - 30
		if ($row == 1) {
			return true;
		}
		return false;
	}
}

class Vtiger_QuickExport_Action extends Vtiger_Mass_Action
{

	public function checkPermission(Vtiger_Request $request)
	{
		$currentUserPriviligesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!$currentUserPriviligesModel->hasModuleActionPermission($request->getModule(), 'QuickExportToExcel')) {
			throw new \Exception\NoPermitted('LBL_PERMISSION_DENIED');
		}
	}

	public function __construct()
	{
		$this->exposeMethod('ExportToExcel');
		$this->exposeMethod('ExportToTemplate');
	}

	public function process(Vtiger_Request $request)
	{
		$mode = $request->getMode();

		if ($mode) {
			$this->invokeExposedMethod($mode, $request);
		}
	}

	public function ExportToTemplate(Vtiger_Request $request)
	{
		$excelTemplatePath = AppConfig::performance('EXCEL_TEMPLATE_PATH') . DIRECTORY_SEPARATOR . $request->getModule(false);
		$errorMessage = 'Ci scusiamo per il disagio ma non e` stato possibile generare la stampa che avete richiesto, riprovare in un secondo momento. Se il problema persiste contattare il servizio clienti di Simple Solutions. Grazie';

		if (!is_dir($excelTemplatePath)) {
			throw new \Exception\AppException($errorMessage);
			return;
		}

		$filesIterator = new FilesystemIterator($excelTemplatePath, FilesystemIterator::SKIP_DOTS);
		if (iterator_count($filesIterator) == 0) {
			throw new \Exception\AppException($errorMessage);
			return;
		}
		$selectedFiles = $request->get('selectedFiles');
		if(count($selectedFiles) < 1 || !is_array($selectedFiles)) {
			throw new \Exception\AppException($errorMessage);
			return false;
		}

		$module = $request->getModule(false); //this is the type of things in the current view
		$recordIds = $this->getRecordsListFromRequest($request); //this handles the 'all' situation.

		$today =  date("d-m-Y");
		$templateFiles =[];
		foreach ($filesIterator as $fileInfo) {
			$extension          = $fileInfo->getExtension();
			$pathname           = $fileInfo->getPathname();
			$filename           = $fileInfo->getFilename();
			$filenameWithoutExt = basename($filename,".{$extension}");
			if (in_array($filenameWithoutExt, $selectedFiles)) {
				$workbook   = PHPExcel_IOFactory::load($pathname);
				$configurazione = $workbook->getSheetByName('Configurazione');
				if (empty($configurazione)) {
					$workbook->disconnectWorksheets();
					unset($workbook);
					continue;
				}
				$workType    = !empty($configurazione->getCell('A2')->getValue()) ? $configurazione->getCell('A2')->getValue() : 'multiplo';
				$writeType   = !empty($configurazione->getCell('B2')->getValue()) ? $configurazione->getCell('B2')->getValue() : 'xlxs';

				switch (strtolower($workType)) {
					case 'singolo':
						$newFilename = "{$today}_{$filenameWithoutExt}";
						$this->setWorkbookFromTemplate($workbook, $module, $recordIds);
						$templateFiles = array_merge($templateFiles, $this->saveWorkbook($workbook, $newFilename, $writeType, $sheetConf));
						break;
					case 'multiplo':
					default:
						foreach ($recordIds as $id) {
							/* $workbook   = PHPExcel_IOFactory::load($pathname); */
							$newFilename = "{$today}_{$id}_{$filenameWithoutExt}";
							$workbookClone  = $workbook;
							$this->setWorkbookFromTemplate($workbookClone, $module, [$id]);
							$templateFiles = array_merge($templateFiles, $this->saveWorkbook($workbookClone, $newFilename, $writeType, $sheetConf));
							$workbookClone->disconnectWorksheets();
							unset($workbookClone);
						}
						break;
				}
				$workbook->disconnectWorksheets();
				unset($workbook);
			}
		}

		if(count($templateFiles) > 1) {
			$this->zipAndDownload($templateFiles);
		} elseif (count($templateFiles) == 1) {
			foreach ($templateFiles as $key => $value) {
				if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
					header('Pragma: public');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				}

				$mimeType = \App\Fields\File::getMimeContentType($value);
				header('Content-Type: ' . $mimeType);
				header('Content-Length: ' . @filesize($value));
				header("Content-Disposition: attachment; filename=\"$key\"");

				$fp = fopen($value, 'rb');
				fpassthru($fp);
				fclose($fp);
				unlink($value);
			}
		} else {
			throw new \Exception\AppException($errorMessage);
			return;
		}
	}


	private function saveWorkbook(&$workbook, $name, $writeType = 'xlsx')
	{
		$configurazione = $workbook->getSheetByName('Configurazione');
		$highestRow     = $configurazione->getHighestRow();
		$highestColumn  = $configurazione->getHighestColumn();
		$range          = "A5:{$highestColumn}{$highestRow}";
		$dataConfig     = $configurazione->rangeToArray($range);
		foreach ($dataConfig as $conf) {
			$sheetConf[] = [
				'name'  	=> $conf[0],
				'printArea' => $conf[1],
				'active'    => $conf[2],
				'visible'   => $conf[3],
			];
		}
		$configurazione->setSheetState(PHPExcel_Worksheet::SHEETSTATE_HIDDEN);

		foreach ($sheetConf as $sheet) {
			if(!empty($sheet['name'])) {
				$worksheet = $workbook->getSheetByName($sheet['name']);
				$worksheet->setShowGridlines(false);
				if (!empty($sheet['printArea'])) {
					$worksheet->getPageSetup()->setPrintArea($sheet['printArea']);
				}
				if (!empty($sheet['active'])) {
					$workbook->setActiveSheetIndexByName($sheet['name']);
				}
				if (!empty($sheet['visible'])) {
					$worksheet->setSheetState(PHPExcel_Worksheet::SHEETSTATE_VISIBLE);
				} else {
					$worksheet->setSheetState(PHPExcel_Worksheet::SHEETSTATE_HIDDEN);
				}
			}
		}

		$tmpDir = AppConfig::main('tmp_dir');
		switch (strtolower($writeType)) {
			case 'pdf':
				/* $styleArray = array( */
				/* 	'borders' => array( */
				/* 		'top' => array( */
				/* 			'style' => PHPExcel_Style_Border::BORDER_NONE, */
				/* 		), */
				/* 	), */
				/* ); */
				/* $workbook->getActiveSheet()->getStyle('A1:L58')->applyFromArray($styleArray); */
				/* $worksheet = $workbook->getSheet(1); */
				/* $worksheet->removeRow(1); */
				/* $worksheet->removeColumn('A'); */
				$tempFileName = tempnam(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $tmpDir, 'pdf');
				$rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
				$rendererLibraryPath = '/var/www/grenti/libraries/mPDF/';
				if (!PHPExcel_Settings::setPdfRenderer(
						$rendererName,
						$rendererLibraryPath
					)) {
					return false;
				}
				$workbookWriter = new PHPExcel_Writer_PDF($workbook);
				$workbookWriter->writeAllSheets();
				$workbookWriter->save($tempFileName);
				$newFilename = "{$name}.pdf";
				break;
			case 'xlsx':
			default:
				$tempFileName = tempnam(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $tmpDir, 'xlsx');
				$workbookWriter = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');
				$workbookWriter->save($tempFileName);
				$newFilename = "{$name}.xlsx";
				break;
		}

		return [$newFilename => $tempFileName];
	}

	private function setWorkbookFromTemplate(&$workbook, $moduleName, $ids)
	{
		$loadedSheetNames = $workbook->getSheetNames();

		$validLocale = PHPExcel_Settings::setLocale('it');
		if (!$validLocale) {
			die();
		}

		if (in_array($moduleName, $loadedSheetNames)) {
			$worksheet = $workbook->getSheetByName($moduleName);
			$this->setModuleWorksheet($worksheet, $moduleName, $ids);
		}

		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$allRelationModel = Vtiger_Relation_Model::getAllRelations($moduleModel);
		$headers = $moduleModel->getFields();

		$relatedSheetIds= [];
		foreach ($ids as $id) {
			if(!empty($id) && App\Record::isExists($id)) {
				//Estrazione record relazionati 1:M
				$recordModel = Vtiger_Record_Model::getInstanceById($id, $moduleName);
				foreach ($headers as $fieldModel) {
					$fieldName = $fieldModel->get('name');
					if ($fieldModel->isReferenceField()) {
						$relatedId = $recordModel->get($fieldName);
						if(!empty($relatedId) && App\Record::isExists($relatedId)) {
							$relatedRecordModel = Vtiger_Record_Model::getInstanceById($relatedId);
							$relatedModuleName = $relatedRecordModel->getModuleName();
							if (in_array($relatedModuleName, $loadedSheetNames)) {
								$relatedSheetIds[$relatedModuleName]['ids'][] = $relatedId;
								$relatedSheetIds[$relatedModuleName]['parentids'][] = $id;
							}
						}
					}
				}

				//Estrazione record relazionati M:M
				foreach($allRelationModel as $relationModel) {
					$relatedModuleName = $relationModel->getRelationModuleName();
					if (in_array($relatedModuleName, $loadedSheetNames)) {
						$recordModel = Vtiger_Record_Model::getInstanceById($id, $moduleName);
						$relationListView  = Vtiger_RelationListView_Model::getInstance($recordModel, $relatedModuleName);
						$relatedIds          = $relationListView->getRelationQuery()->select(['vtiger_crmentity.crmid'])
						->distinct()
						->column();

						if(count($relatedIds)) {
							foreach($relatedIds as $relatedId) {
								if(!empty($relatedId) && App\Record::isExists($relatedId)) {
									$relatedSheetIds[$relatedModuleName]['ids'][] = $relatedId;
									$relatedSheetIds[$relatedModuleName]['parentids'][] = $id;
								}
							}
						}
					}
				}
			}
		}

		foreach ($relatedSheetIds as $module => $relatedId) {
			$worksheet = $workbook->getSheetByName($module);
			$this->setModuleWorksheet($worksheet, $module, $relatedSheetIds[$module]['ids'] ,$relatedSheetIds[$module]['parentids']);
		}
	}

	private function setModuleWorksheet(&$worksheet, $module, $ids, $idsRelated = false)
	{
		$moduleModel = Vtiger_Module_Model::getInstance($module);
		$headers = $moduleModel->getFields();
		$header_styles = [
			'fill' => ['type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => ['rgb' => 'E1E0F7']],
			'font' => ['bold' => true]
		];
		$row = 1;
		$col = 0;

		if (!empty($idsRelated)) {
			$worksheet->setCellValueExplicitByColumnAndRow($col, $row, 'parent_id', PHPExcel_Cell_DataType::TYPE_STRING);
			$col++;
		}
		$worksheet->setCellValueExplicitByColumnAndRow($col, $row, 'id', PHPExcel_Cell_DataType::TYPE_STRING);
		$col++;
		foreach ($headers as $fieldsModel) {
			$worksheet->setCellValueExplicitByColumnAndRow($col, $row, decode_html(App\Language::translate($fieldsModel->getFieldLabel(), $module)), PHPExcel_Cell_DataType::TYPE_STRING);
			$col++;
		}
		$row++;

		//ListViewController has lots of paging stuff and things we don't want
		//so lets just itterate across the list of IDs we have and get the field values
		if(!is_array($ids)) {
			$newArr = [];
			$newArr[] = $ids;
			$ids = $newArr;
		}
		foreach ($ids as $key => $id) {
			$col = 0;
			if(!\App\Record::isExists($id)) {
				continue;
			}
			$record = Vtiger_Record_Model::getInstanceById($id, $module);
			if (!empty($idsRelated)) {
				$idRel =!empty($idsRelated[$key]) ? $idsRelated[$key] : 0;
				$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, $idRel, PHPExcel_Cell_DataType::TYPE_STRING);
				$col++;
			}

			$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, $id, PHPExcel_Cell_DataType::TYPE_STRING);
			$col++;
			foreach ($headers as $fieldsModel) {
				//depending on the uitype we might want the raw value, the display value or something else.
				//we might also want the display value sans-links so we can use strip_tags for that
				//phone numbers need to be explicit strings
				$value = $record->getDisplayValue($fieldsModel->getFieldName(), $id, true);
				switch ($fieldsModel->getUIType()) {
					case 25:
					case 7:
						if ($fieldsModel->getFieldName() === 'sum_time') {
							$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($value), PHPExcel_Cell_DataType::TYPE_STRING);
						} else {
							$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($value), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						}
						break;
					case 71:
					case 72:
						$rawValue = $record->get($fieldsModel->getFieldName());
						$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($rawValue), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						break;
					case 6://datetimes
					case 23:
					case 70:
						$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, PHPExcel_Shared_Date::PHPToExcel(strtotime($record->get($fieldsModel->getFieldName()))), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						$worksheet->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS'); //format the date to the users preference
						break;
					default:
						$worksheet->setCellValueExplicitByColumnAndRow($col, $row, decode_html(strip_tags($value)), PHPExcel_Cell_DataType::TYPE_STRING);
				}
				$col++;
			}
			$row++;
		}

		//having written out all the data lets have a go at getting the columns to auto-size
		$col = 1;
		$row = 1;
		foreach ($headers as $fieldsModel) {
			$cell = $worksheet->getCellByColumnAndRow($col, $row);
			$worksheet->getStyleByColumnAndRow($col, $row)->applyFromArray($header_styles);
			$worksheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
			$col++;
		}


	}

	private function zipAndDownload(array $fileNames)
	{

		//create the object
		$zip = new ZipArchive();

		mt_srand(time());
		$postfix = time() . '_' . mt_rand(0, 1000);
		$zipPath = 'storage/';
		$zipName = "pdfZipFile_{$postfix}.zip";
		$fileName = $zipPath . $zipName;

		//create the file and throw the error if unsuccessful
		if ($zip->open($zipPath . $zipName, ZIPARCHIVE::CREATE) !== true) {
			\App\Log::error("cannot open <$zipPath.$zipName>\n");
			throw new \Exception\NoPermitted("cannot open <$zipPath.$zipName>");
		}

		//add each files of $file_name array to archive
		foreach ($fileNames as $key => $file) {
			$zip->addFile($file, $key);
		}
		$zip->close();

		// delete added pdf files
		foreach ($fileNames as $file) {
			unlink($file);
		}
		$mimeType = \App\Fields\File::getMimeContentType($fileName);
		$size = filesize($fileName);
		$name = basename($fileName);

		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		header("Content-Type: $mimeType");
		header('Content-Disposition: attachment; filename="' . $name . '";');
		header("Accept-Ranges: bytes");
		header('Content-Length: ' . $size);

		print readfile($fileName);
		// delete temporary zip file and saved pdf files
		unlink($fileName);
	}

	public function ExportToExcel(Vtiger_Request $request)
	{
		$module = $request->getModule(false); //this is the type of things in the current view
		$filter = $request->get('viewname'); //this is the cvid of the current custom filter
		$recordIds = $this->getRecordsListFromRequest($request); //this handles the 'all' situation.
		//set up our spreadsheet to write out to
		$workbook = new PHPExcel();
		$worksheet = $workbook->setActiveSheetIndex(0);
		$header_styles = [
			'fill' => ['type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => ['rgb' => 'E1E0F7']],
			'font' => ['bold' => true]
		];
		$row = 1;
		$col = 0;

		$queryGenerator = new \App\QueryGenerator($module);
		$queryGenerator->initForCustomViewById($filter);
		$headers = $queryGenerator->getListViewFields();
		$customView = CustomView_Record_Model::getInstanceById($filter);
		//get the column headers, they go in row 0 of the spreadsheet
		foreach ($headers as &$fieldsModel) {
			$worksheet->setCellValueExplicitByColumnAndRow($col, $row, decode_html(App\Language::translate($fieldsModel->getFieldLabel(), $module)), PHPExcel_Cell_DataType::TYPE_STRING);
			$col++;
		}
		$row++;

		//ListViewController has lots of paging stuff and things we don't want
		//so lets just itterate across the list of IDs we have and get the field values
		foreach ($recordIds as $id) {
			$col = 0;
			$record = Vtiger_Record_Model::getInstanceById($id, $module);
			foreach ($headers as &$fieldsModel) {
				//depending on the uitype we might want the raw value, the display value or something else.
				//we might also want the display value sans-links so we can use strip_tags for that
				//phone numbers need to be explicit strings
				$value = $record->getDisplayValue($fieldsModel->getFieldName());
				switch ($fieldsModel->getUIType()) {
					case 25:
					case 7:
						if ($fieldsModel->getFieldName() === 'sum_time') {
							$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($value), PHPExcel_Cell_DataType::TYPE_STRING);
						} else {
							$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($value), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						}
						break;
					case 71:
					case 72:
						$rawValue = $record->get($fieldsModel->getFieldName());
						$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, strip_tags($rawValue), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						break;
					case 6://datetimes
					case 23:
					case 70:
						$worksheet->setCellvalueExplicitByColumnAndRow($col, $row, PHPExcel_Shared_Date::PHPToExcel(strtotime($record->get($fieldsModel->getFieldName()))), PHPExcel_Cell_DataType::TYPE_NUMERIC);
						$worksheet->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS'); //format the date to the users preference
						break;
					default:
						$worksheet->setCellValueExplicitByColumnAndRow($col, $row, decode_html(strip_tags($value)), PHPExcel_Cell_DataType::TYPE_STRING);
				}
				$col++;
			}
			$row++;
		}

		//having written out all the data lets have a go at getting the columns to auto-size
		$col = 0;
		$row = 1;
		foreach ($headers as &$fieldsModel) {
			$cell = $worksheet->getCellByColumnAndRow($col, $row);
			$worksheet->getStyleByColumnAndRow($col, $row)->applyFromArray($header_styles);
			$worksheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
			$col++;
		}

		$tmpDir = vglobal('tmp_dir');
		$tempFileName = tempnam(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $tmpDir, 'xls');
		$workbookWriter = PHPExcel_IOFactory::createWriter($workbook, 'Excel5');
		$workbookWriter->save($tempFileName);
		$workbook->disconnectWorksheets();
		unset($workbook);

		if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
			header('Pragma: public');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		}

		header('Content-Type: application/x-msexcel');
		header('Content-Length: ' . @filesize($tempFileName));
		$filename = vtranslate($module, $module) . '-' . vtranslate(decode_html($customView->get('viewname')), $module) . ".xls";
		header("Content-Disposition: attachment; filename=\"$filename\"");

		$fp = fopen($tempFileName, 'rb');
		fpassthru($fp);
		fclose($fp);
		unlink($tempFileName);
	}

	public function validateRequest(Vtiger_Request $request)
	{
		$request->validateWriteAccess();
	}
}
