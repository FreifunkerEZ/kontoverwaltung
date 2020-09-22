<?php
/**
 * calls the right function, based on the requested action.
 * @param string $action
 * @param CashDB $db
 */
function routeAction($action, $db) {
	switch ($action) {
		case 'upload':
			$db->importProcessUpload();
			break;
		case 'buchungEdit':
			$db->buchungEdit();
			break;
		case 'tagSave':
			$db->tagSave();
			break;
		case 'tagDelete':
			$db->tagDelete();
			break;
		case 'ruleSave':
			$db->ruleSave();
			break;
		case 'ruleDelete':
			$db->ruleDelete();
			break;
		case 'ruleApply':
			if (!isset($_POST['ruleID']))
				throw new Exception("no rule id, no applying");
			$db->ruleApply($_POST['ruleID']);
			break;
		case 'restoreTodaysBackup':
			$db->dbBackupRestore('today');
			break;
		case 'findHoles':
			$db->findHoles();
			break;

		default:
			throw new Exception("Unknown action $action");
			break;
	}
}
?>