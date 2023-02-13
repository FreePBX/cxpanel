<?php
namespace FreePBX\modules\Cxpanel;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction)
	{
		$depends = array('userman', 'manager');
		foreach($depends as $dependency){
			$this->addDependency($dependency);
		}
		
		$configs = [
			'kvstore' => $this->dumpKVStore(),
			'tables'  => $this->dumpTables(),
		];
		$this->addConfigs($configs);
	}
}