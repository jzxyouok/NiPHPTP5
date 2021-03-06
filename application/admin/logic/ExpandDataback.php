<?php
/**
 *
 * 数据备份 - 扩展 - 逻辑层
 *
 * @package   NiPHPCMS
 * @category  admin\logic\
 * @author    失眠小枕头 [levisun.mail@gmail.com]
 * @copyright Copyright (c) 2013, 失眠小枕头, All rights reserved.
 * @version   CVS: $Id: ExpandDataback.php v1.0.1 $
 * @link      http://www.NiPHP.com
 * @since     2016/11/05
 */
namespace app\admin\logic;

use think\Model;
use think\Request;
use think\Config;
use util\File;
use util\Pclzip;

class ExpandDataback extends Model
{
    protected $request = null;
    protected $not_back_table = [
        'admin',
        'access',
        'action',
        'action_log',
        'config',
        'node',
        'role',
        'role_admin',
        'searchengine',
        'visit',
    ];

    protected function initialize()
    {
        parent::initialize();

        $this->request = Request::instance();
    }

    /**
     * 列表数据
     * @access public
     * @param
     * @return array
     */
    public function getListData()
    {
        $list = File::get(ROOT_PATH . 'public' . DS . 'backup' . DS);

        rsort($list);

        // 删除过期备份
        $days = strtotime('-180 days');
        foreach ($list as $key => $value) {
            if (strtotime($value['time']) <= $days) {
                File::delete(ROOT_PATH . 'public' . DS . 'backup' . DS . $value['name']);
                unset($list[$key]);
            } else {
                $list[$key]['id'] = encrypt($value['name']);
            }
        }

        return $list;
    }

    /**
     * 备份数据库
     * @access public
     * @param
     * @return boolean
     */
    public function createZipSql($limit_=7000)
    {
        $dir = TEMP_PATH . 'BACK' . date('YmdHis') . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $tables = $this->getTables();
        $tables_sql = '';

        foreach ($this->not_back_table as $key => $value) {
            $this->not_back_table[$key] = Config::get('database.prefix') . $value;
        }

        foreach ($tables as $table) {
            if (in_array($table, $this->not_back_table)) {
                continue;
            }
            $tableRs = $this->query('SHOW CREATE TABLE `' . $table . '`');
            if (!empty($tableRs[0]['Create Table'])) {
                $tables_sql .= "DROP TABLE IF EXISTS `{$table}`;\r\n";
                $tables_sql .= $tableRs[0]['Create Table'] . ";\r\n";

                $field = $this->query('SHOW COLUMNS FROM `' . $table . '`');
                $fieldRs = array();
                foreach ($field as $val) {
                    $fieldRs[] = $val['Field'];
                }

                $this->table($table);
                $count =
                $this->count();

                $count = ceil($count / $limit_);
                for ($i=0; $i < $count; $i++) {
                    $firstRow = $i * $limit_;

                    $result =
                    $this->table($table)
                    ->field($fieldRs)
                    ->limit($firstRow, $limit_)
                    ->select();

                    $table_data = [];
                    foreach ($result as $key => $value) {
                        $table_data[] = $value->toArray();
                    }

                    $insert_sql = "INSERT INTO `{$table}` (`" . implode('`,`', $fieldRs) . "`) VALUES ";
                    $values = array();
                    foreach ($table_data as $data) {
                        if (!empty($data['delete_time']) && !is_null($data['delete_time'])) {
                            $data['delete_time'] = strtotime($data['delete_time']);
                        }
                        if (array_key_exists("delete_time",$data) && is_null($data['delete_time'])) {
                            $data['delete_time'] = 'null';
                        }

                        if (isset($data['create_time'])) {
                            $data['create_time'] = strtotime($data['create_time']);
                        }

                        if (isset($data['update_time'])) {
                            $data['update_time'] = strtotime($data['update_time']);
                        }

                        if (isset($data['show_time'])) {
                            $data['show_time'] = (float) strtotime($data['show_time']);
                        }

                        $values[] = '(\'' . implode('\',\'', $data) . '\')';
                    }
                    $insert_sql .= implode(',', $values) . ';';
                    $insert_sql = strtr($insert_sql, ['\'NULL\''=>'NULL']);

                    $num = 700001 + $i;
                    file_put_contents($dir . $table . '_' . $num . '.sql', $insert_sql);
                }
            }
        }
        file_put_contents($dir . 'tables.sql', $tables_sql);

        // 打包备份
        $zip = new Pclzip('');
        $zip->zipname = ROOT_PATH . 'public' . DS . 'backup' . DS . 'back ' . date('YmdHis') . '.zip';
        $zip->create($dir, PCLZIP_OPT_REMOVE_PATH, $dir);

        // 删除临时文件
        File::delete($dir);

        return true;
    }

    /**
     * 优化/修复表
     * @access public
     * @param
     * @return boolean
     */
    public function optimize()
    {
        $optimize = TEMP_PATH . 'optimize.php';

        // 上次执行时间
        // 无时赋值一月前时间
        if (file_exists($optimize)) {
            $time = include($optimize);
        } else {
            $time = strtotime('-30 days');
        }

        // 执行时间大于一个月前时不执行
        if ($time > strtotime('-30 days')) {
            return $time;
        }

        // 记录执行时间
        $time = strtotime(date('Y-m-d H:i:s'));
        file_put_contents($optimize, '<?php return ' . $time . ';');

        $tables = $this->getTables();
        $tables_sql = '';

        $sql = 'REPAIR TABLE `' . implode('`,`', $tables) . '`;';
        $sql .= ' OPTIMIZE TABLE `' . implode('`,`', $tables) . '`;';
        $this->batchQuery($sql);

        return true;
    }

    /**
     * 获得库中所有表
     * @access private
     * @param
     * @return array
     */
    private function getTables()
    {
        $result = $this->query('SHOW TABLES FROM ' . Config::get('database.database'));
        $tables = array();
        foreach ($result as $key => $value) {
            $tables[] = current($value);
        }
        return $tables;
    }

    /**
     * 还原数据
     * @access public
     * @param
     * @return boolean
     */
    public function reduction()
    {
        $file = decrypt($this->request->param('id'));
        $name = explode('.', $file);

        $dir = TEMP_PATH . $name[0] . DS;

        $zip = new Pclzip('');
        $zip->zipname = ROOT_PATH . 'public' . DS . 'backup' . DS . $file;
        $zip->extract(PCLZIP_OPT_PATH, $dir);

        $list = File::get($dir . DS);
        foreach ($list as $key => $value) {
            if ($value['name'] == 'tables.sql') {
                $sql = file_get_contents($dir . $value['name']);
                unset($list[$key]);
            }
        }
        $execute_table_sql = explode(';', $sql);
        array_pop($execute_table_sql);
        $this->batchQuery($execute_table_sql);

        foreach ($list as $key => $value) {
            $execute_insert_sql = [file_get_contents($dir . $value['name'])];
            $this->batchQuery($execute_insert_sql);
        }

        // 删除临时文件
        File::delete($dir);

        return true;
    }
}
