<?php
/* {[The file is published on the basis of YetiForce Public License that can be found in the following directory: licenses/License.html]} */

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
		$errorMessage = 'Ci scusiamo per il disagio ma non e` stato possibile generare il file excel che avete richiesto. Per maggiori informazioni contattare il servizio clienti di Simple Solutions. Grazie';

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
		if(count($selectedFiles) < 1 || !is_array($selectedfiles)) {
			/* throw new \Exception\AppException($errorMessage); */
			return false;
		}

		vimport('libraries.PHPExcel.PHPExcel');
		$module = $request->getModule(false); //this is the type of things in the current view
		$recordIds = $this->getRecordsListFromRequest($request); //this handles the 'all' situation.

		$excelFiles =[];
		$tmpDir = AppConfig::main('tmp_dir');
		foreach ($filesIterator as $fileInfo) {
			$extension = $fileInfo->getExtension();
			$pathname = $fileInfo->getPathname();
			$filename = $fileInfo->getFilename();
			$filenameWithoutExt = basename($filename,".{$extension}");
			if ($extension === "xlsx" || $extension === "xls" && in_array($filenameWithoutExt, $selectedFiles)) {
				foreach ($recordIds as $id) {
					$workbook = $this->setExcelTemplate($id,$module,$pathname);

					$tempFileName = tempnam(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . $tmpDir, 'xlsx');

					/* $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF; */
					/* $rendererLibraryPath = '/var/www/grenti/libraries/mPDF/'; */
					/* if (!PHPExcel_Settings::setPdfRenderer( */
					/* 		$rendererName, */
					/* 		$rendererLibraryPath */
					/* 	)) { */
					/* 	die( */
					/* 		'Please set the $rendererName and $rendererLibraryPath values' . */
					/* 		PHP_EOL . */
					/* 		' as appropriate for your directory structure' */
					/* 	); */
					/* } */
					/* $workbookWriter = new PHPExcel_Writer_PDF($workbook); */
					/* /1* $workbookWriter->writeAllSheets(); *1/ */
					/* $objWriter->setPreCalculateFormulas(false); */
					/* $workbookWriter->setSheetIndex(0); */
					/* $workbookWriter->save($tempFileName); */

					$workbookWriter = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');
					$workbookWriter->save($tempFileName);
					$workbook->disconnectWorksheets();
					unset($workbook);
					$filename = "{$module}_{$id}_{$filenameWithoutExt}.xlsx";
					$excelFiles[$filename] = $tempFileName;
				}
			}
		}

		if(count($excelFiles) > 1) {
			$this->zipAndDownload($excelFiles);
		} elseif (count($excelFiles) == 1) {
			foreach ($excelFiles as $key => $value) {
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
		}
	}

	private function setExcelTemplate($id, $moduleName, $pathname)
	{
		$workbook = PHPExcel_IOFactory::load($pathname);
		$loadedSheetNames = $workbook->getSheetNames();

		$activeSheet = $workbook->getSheet(0)->getCell('A3')->getValue();
		if (in_array($activeSheet, $loadedSheetNames)) {
			$workbook->setActiveSheetIndexByName($activeSheet);
		}

		foreach ($loadedSheetNames as $sheet) {
			$printArea = $workbook->getSheetByName($sheet)->getCell('A1')->getCalculatedValue();
			if (strpos($printArea, ':') !== FALSE) {
				$workbook->getSheetByName($sheet)->getPageSetup()->setPrintArea($printArea);
			}
		}

		$validLocale = PHPExcel_Settings::setLocale('it');
		if (!$validLocale) {
			echo 'Unable to set locale to '.$locale." - reverting to en_us<br />\n";
			die();
		}

		if (in_array($moduleName, $loadedSheetNames)) {
			$worksheet   = $workbook->getSheetByName($moduleName);
			$this->setWorksheetForModule($id, $moduleName, $worksheet);
		}

		$recordModel = Vtiger_Record_Model::getInstanceById($id, $moduleName);
		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$headers = $moduleModel->getFields();
		foreach ($headers as $fieldModel) {
			$fieldName = $fieldModel->get('name');
			if ($fieldModel->isReferenceField()) {
				$relatedId = $recordModel->get($fieldName);
				if(!empty($relatedId) && App\Record::isExists($relatedId)) {
					$relatedRecordModel = Vtiger_Record_Model::getInstanceById($relatedId);
					if (in_array($relatedRecordModel->getModuleName(), $loadedSheetNames)) {
						$worksheet = $workbook->getSheetByName($relatedRecordModel->getModuleName());
						$this->setWorksheetForModule($relatedId, $relatedRecordModel->getModuleName(), $worksheet);
					}
				}
			}
		}

		$allRelationModel = Vtiger_Relation_Model::getAllRelations($moduleModel);
		foreach($allRelationModel as $relationModel) {
			$relatedModuleName = $relationModel->getRelationModuleName();
			if (in_array($relatedModuleName, $loadedSheetNames)) {
				$relationListView  = Vtiger_RelationListView_Model::getInstance($recordModel, $relatedModuleName);
				$relationIds          = $relationListView->getRelationQuery()->select(['vtiger_crmentity.crmid'])
				->distinct()
				->column();

				if(count($relationIds)) {
					$worksheet = $workbook->getSheetByName($relatedModuleName);
					$this->setWorksheetForModule($relationIds, $relatedModuleName, $worksheet);
				}
			}
		}
		return $workbook;
	}

	private function setWorksheetForModule($ids, $module, &$worksheet)
	{
		$moduleModel = Vtiger_Module_Model::getInstance($module);
		$headers = $moduleModel->getFields();
		$header_styles = [
			'fill' => ['type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => ['rgb' => 'E1E0F7']],
			'font' => ['bold' => true]
		];
		$row = 1;
		$col = 0;


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
		foreach ($ids as $id) {
			$col = 0;
			if(!\App\Record::isExists($id)) {
				continue;
			}
			$record = Vtiger_Record_Model::getInstanceById($id, $module);
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
		vimport('libraries.PHPExcel.PHPExcel');
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
