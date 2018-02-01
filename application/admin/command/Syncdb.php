<?php
namespace app\admin\command;

use app\admin\model\AuthRule;
use ReflectionClass;
use ReflectionMethod;
use think\Cache;
use think\Config;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
class Syncdb extends Command {

	protected function configure() {
		$this
		->setName('syncdb')
		->addOption('update', 'u', Option::VALUE_REQUIRED, 'sync table', null)
		->setDescription('Build sync table');
	}

	protected function execute(Input $input,Output $output)
	{
		$adminPath = dirname(__DIR__) . DS;
		$table = $input->getOption('update');
		if (!$table)
        {
            throw new Exception('table name can\'t empty');
        }
        if(!file_exists($adminPath.'dbschema/'.$table.'.php')) {
        	throw new Exception($adminPath.'dbschema/'.$table.'.php not file');
        }
        $dbname = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $tableInfo =Db::query("SHOW TABLES LIKE '".$prefix.$table."'", [], TRUE);
        
		include $adminPath.'dbschema/'.$table.'.php';
		$dbData = $db[$table];
		
		if(!$tableInfo) {
			$createTable = "CREATE TABLE `{$prefix}{$table}` ( \n";	
			//无表情况
        	foreach ($dbData['columns'] as $key => $value) {

				$is_multiselect = isset($value['is_multiselect']) && $value['is_multiselect']===true  ? true :false;
				
				$type = $this->_getDbType($value['type'],$is_multiselect);
				if(isset($value['autoincrement']) && $value['autoincrement'] == true) {
					$autoincrement = 'AUTO_INCREMENT';
				}else {
					$autoincrement ='';
				}
				if(isset($value['required']) && $value['required']===true) {
					$required = 'NOT NULL';
				}else {
					$required = '';
				}
				
				if(isset($value['default']) && is_string($value['default']) && $value['default']!='') {
					$default = "DEFAULT '{$value['default']}'";
				}elseif (isset($value['default']) && !is_string($value['default']) && $value['default']!='') {
					$default = "DEFAULT {$value['default']}";
				}else {
					$default = '';
				}
				
				$createTable.= "`{$key}` ".$type." ".$required." ".$default." ".$autoincrement." COMMENT '{$value['comment']}', \n";
			}
			if(isset($dbData['primary'])) {
				$createTable.= " PRIMARY KEY (`".$dbData['primary']."`),\n";
			}
			
			if(isset($dbData['index']) && is_array($dbData['index'])) {
				foreach ($dbData['index'] as $key => $v) {
					
					$createTable.=" KEY `ind_".$v['columns']."` (`".$v['columns']."`),\n";
					$index_columns[] = $v['columns'];
				}
				$createTable = substr($createTable,0,strrpos($createTable,','))."\n";
				
			}else {
				$createTable = substr($createTable,0,strrpos($createTable,','))."\n";
			}
			
			$createTable.=" )ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci\n";


			
			//设置字段缓存
			Cache::set('cache_columns_'.$table,array_keys($dbData['columns']),0);
			Cache::set('cache_index_'.$table,$index_columns,0);
			$output->info($createTable);
			try {

	            Db::query($createTable,[],true);

	        } catch (\Exception $e) {
	           throw new Exception($e->getMessage());exit;
	        }
	        $output->info("Build Successed");
        }else {
        	//有表情况 todo
        	//字段缓存
        	$cache_table_columns = Cache::get('cache_columns_'.$table);
        	//索引缓存
        	$cache_index_columns = Cache::get('cache_index_'.$table);

        	//查询索引是否有变化
        	if(isset($dbData['index']) && is_array($dbData['index'])) {
        		foreach ($dbData['index'] as $key => $value) {
        			$new_index_columns[]=$value['columns'];
        		}
        	}
        	//要删除的索引
        	$drop_index_columns = array_diff($cache_index_columns,$new_index_columns);
        	//要添加的索引
        	$add_index_columns = array_diff($new_index_columns,$cache_index_columns);
        	
        	$new_columns = array_keys($dbData['columns']);
        	$drop_columns = array_diff($cache_table_columns,$new_columns);
        	$add_columns = array_diff($new_columns,$cache_table_columns);

        	if(empty($drop_columns) && empty($add_columns)) {
        		throw new Exception('not syncdb update');exit;
        	}
        	
        	//要删除的字段
        	if($drop_columns) {
        		$drop_string ='';
	        	foreach ($drop_columns as $dk => $dv) {
	        		$drop_string.= " DROP COLUMN ".$dv.",";
	        	}
	        	$drop_string = rtrim($drop_string,',');
	        	$drop_sql = "ALTER TABLE ".$prefix.$table.$drop_string."\n";
	        	$output->info($drop_sql);
	        	try {
	        		Db::query($drop_sql,[],true);

	        	} catch (\Exception $e) {
	        		 throw new Exception($e->getMessage());exit;
	        	}
        	}
        	
        	
        	
        	//要删除的字段end
        	//新增的字段
        	$add_string = '';
        	foreach ($add_columns as $ak => $av) {

        		
				$is_multiselect = isset($dbData['columns'][$av]['is_multiselect']) && $dbData['columns'][$av]['is_multiselect']===true  ? true :false;
				$type = $this->_getDbType($dbData['columns'][$av]['type'],$is_multiselect);

				if(isset($dbData['columns'][$av]['required']) && $dbData['columns'][$av]['required']===true) {
					$required = 'NOT NULL';
				}else {
					$required = '';
				}
				
				if(isset($dbData['columns'][$av]['default']) && is_string($dbData['columns'][$av]['default']) && $dbData['columns'][$av]['default']!='') {
					$default = "DEFAULT '{$dbData['columns'][$av]['default']}'";
				}elseif (isset($value['default']) && !is_string($value['default']) && $value['default']!='') {
					$default = "DEFAULT {$dbData['columns'][$av]['default']}";
				}else {
					$default = '';
				}
				$add_string.= " ADD ".$av." ".$type.",";
        	}
        	$add_string = rtrim($add_string,',');
        	$add_columnsSql = "ALTER TABLE ".$prefix.$table." ".$add_string." ".$required." ".$default." COMMENT '".$dbData['columns'][$av]['comment']."'\n";
        	$output->info($add_columnsSql);
        	try {
        		Db::query($add_columnsSql,[],true);

        	} catch (\Exception $e) {
        		 throw new Exception($e->getMessage());exit;
        	}

        	//执行要添加的索引
        	$add_index_sql = '';
        	if($add_index_columns) {
        		foreach ($add_index_columns as $ik => $iv) {
        			$add_index_sql .="ALTER TABLE ".$prefix.$table." ADD INDEX index_".$iv." (`".$iv."`); \n"; 
        			try {
        				Db::query("ALTER TABLE ".$prefix.$table." ADD INDEX index_".$iv." (`".$iv."`)",[],true);
		        	} catch (Exception $e) {
		        		throw new Exception($e->getMessage());exit;
		        	}
        		}
        	}
        	$output->info($add_index_sql);
        	
        	Cache::set('cache_columns_'.$table,array_keys($dbData['columns']),0);
        	Cache::set('cache_index_'.$table,$new_index_columns,0);
        	$output->info("Build Successed");
        }
		
		
		
	}
	

	/**
	 * 映射字段类型
	 * @param  [type]  $dbtype         [description]
	 * @param  boolean $is_multiselect [description]
	 * @return [type]                  [description]
	 */
	private function _getDbType($dbtype,$is_multiselect=false) {
		if(is_string($dbtype) && 'number'==$dbtype) {
			$type = 'int(10) unsigned';
		}elseif (is_array($dbtype) && $is_multiselect===true) {
			$type = '';
			foreach ($dbtype as $kv => $vv) {
				$type .="'".$vv."'".",";
			}
			$type = rtrim($type,',');
			$type = "enum(".$type.")";
					
		}elseif (is_string($dbtype) && $dbtype=='string') {
					
			$type = 'varchar(250)';
		}else {
			$type = $dbtype;
		}
		return $type;
	}
}
?>