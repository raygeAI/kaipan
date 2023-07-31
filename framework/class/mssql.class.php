<?php

defined('IN_IA') or exit('Access Denied');

function MsSql()
{
    global $_W;
    static $db;
    if (empty($db)) {
        $db = new MsSql_PDO($_W['config']['mssql']);
    }
    return $db;
}

class MsSql_PDO
{

    private $pdo;
    private $cfg;
    private $errors = array();
    private $debugOut = false;

    public function setDebug($enable)
    {
        $this->debugOut = $enable;
    }

    public function getPDO()
    {
        return $this->pdo;
    }
    
    public function getConfig(){
        return $this->cfg;
    }

    public function clearError()
    {
        $this->errors = array();
    }
    
    public function getErrors(){
        return $this->errors;
    }
    
    public function IsConnected()
    {
        return $this->pdo!=null;
    }

    public function __construct($name = 'mssql')
    {
        global $_W;
        if (is_array($name)) {
            $cfg = $name;
        } else {
            $cfg = $_W['config']['db'][$name];
        }
        if (empty($cfg)) {
            message("没有找到名为 {$name} 的数据库配置项.");
        }
        $dsn = "sqlsrv:server={$cfg['host']}; Database={$cfg['database']}";

        try {
            $this->cfg = $cfg;
            $this->pdo = new PDO($dsn, $cfg['username'], $cfg['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->errors[] = $e->getMessage();
        }


    }


    public function query($sql, $params = array())
    {

        $statement = $this->pdo->prepare($sql);
        if (!is_object($statement)) {
            $this->debug(false, array('sql' => $sql, 'error' => array('', '-1', '当前连接数据库用户没有执行该条语句的权限，请检查mysql权限配置')));
            return false;
        }
        $result = $statement->execute($params);
        if ($this->debugOut) {
            $info = array();
            $info['sql'] = $sql;
            $info['params'] = $params;
            $info['error'] = $statement->errorInfo();
            $this->debug(false, $info);
        }
        if (!$result) {
            return false;
        } else {
            return $statement->rowCount();
        }
    }


    public function fetchcolumn($sql, $params = array(), $column = 0)
    {
        $statement = $this->pdo->prepare($sql);
        if (!is_object($statement)) {
            $this->debug(false, array('sql' => $sql, 'error' => array('', '-1', '当前连接数据库用户没有执行该条语句的权限，请检查mysql权限配置')));
            return false;
        }
        $result = $statement->execute($params);
        if ($this->debugOut) {
            $info = array();
            $info['sql'] = $sql;
            $info['params'] = $params;
            $info['error'] = $statement->errorInfo();
            $this->debug(false, $info);
        }
        if (!$result) {
            return false;
        } else {
            return $statement->fetchColumn($column);
        }
    }


    public function fetch($sql, $params = array())
    {
        $statement = $this->pdo->prepare($sql);
        if (!is_object($statement)) {
            $this->debug(false, array('sql' => $sql, 'error' => array('', '-1', '当前连接数据库用户没有执行该条语句的权限，请检查mysql权限配置')));
            return false;
        }
        $result = $statement->execute($params);
        if ($this->debugOut) {
            $info = array();
            $info['sql'] = $sql;
            $info['params'] = $params;
            $info['error'] = $statement->errorInfo();
            $this->debug(false, $info);
        }
        if (!$result) {
            return false;
        } else {
            return $statement->fetch(pdo::FETCH_ASSOC);
        }
    }


    public function fetchall($sql, $params = array(), $keyfield = '')
    {
        $statement = $this->pdo->prepare($sql);
        if (!is_object($statement)) {
            $this->debug(false, array('sql' => $sql, 'error' => array('', '-1', '当前连接数据库用户没有执行该条语句的权限，请检查mysql权限配置')));
            return false;
        }
        $result = $statement->execute($params);
        if ($this->debugOut) {
            $info = array();
            $info['sql'] = $sql;
            $info['params'] = $params;
            $info['error'] = $statement->errorInfo();
            $this->debug(false, $info);
        }
        if (!$result) {
            return false;
        } else {
            if (empty($keyfield)) {
                return $statement->fetchAll(pdo::FETCH_ASSOC);
            } else {
                $temp = $statement->fetchAll(pdo::FETCH_ASSOC);
                $rs = array();
                if (!empty($temp)) {
                    foreach ($temp as $key => &$row) {
                        if (isset($row[$keyfield])) {
                            $rs[$row[$keyfield]] = $row;
                        } else {
                            $rs[] = $row;
                        }
                    }
                }
                return $rs;
            }
        }
    }


    public function update($table, $data = array(), $params = array(), $glue = 'AND')
    {

        $set=array();
        foreach($data as $k=>$v){
            $set[]="{$k}='{$v}'";
        }
        $where=array();
        foreach($params as $k=>$v){
            $where[]="{$k}='{$v}'";
        }
       
        $sql = "UPDATE " . $this->tablename($table) . " SET " .implode(',',$set);
        $sql .= count($where)>0 ? ' WHERE ' . implode(' and ',$where) : '';
        return $this->runSql($sql);
    }


    public function insert($table, $data = array(), $replace = FALSE)
    {
        $condition = $this->implode($data, ',');
        return $this->query("INSERT INTO " . $this->tablename($table) . " ( {$condition['fields']}) VALUES ( {$condition['params']})");
    }


    public function insertObject($table, $data = array())
    {
        $result = false;
        $fields = array();
        $values = array();
        $statement = 'INSERT INTO ' . $table . ' (%s) VALUES (%s)';
        foreach ($data as $k => $v) {
            if ($k[0] == '_') {
                // Internal field
                continue;
            }

            if ($k == $key && $key == 0) {
                continue;
            }
            $fields[] = $k;
            $values[] = $this->Quote($v);
        }
        // Set the query and execute the insert.
        $sql = sprintf($statement, implode(',', $fields), implode(',', $values));
        return $this->runSql($sql);

    }

    public function runSql($sql){
        logging('sql',$sql);
        $statement = $this->pdo->prepare($sql);
        try {
            $result = $statement->execute();
        } catch (exception $e) {
            $info=array('sql'=> $sql,'error'=>$e->getMessage());
            array_push($this->errors, $info);
            $result = false;
        }
        return $result;
        //$id = $this->insertid();
    }

    public function insertid()
    {
        //'SELECT @@IDENTITY'
        return $this->pdo->lastInsertId();
    }


    public function delete($table, $params = array(), $glue = 'AND')
    {
        $condition = $this->implode($params, $glue);
        $sql = "DELETE FROM " . $this->tablename($table);
        $sql .= $condition['fields'] ? ' WHERE ' . $condition['fields'] : '';
        return $this->query($sql, $condition['params']);
    }


    public function begin()
    {
        $this->pdo->beginTransaction();
    }


    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollback()
    {
        $this->pdo->rollBack();
    }

    public function quote($str)
    {
        return $this->pdo->quote($str);
    }

    private function implode($params, $glue = ',')
    {
        $result = array('fields' => ' 1 ', 'params' => array());
        $split = '';
        $suffix = '';
        if (in_array(strtolower($glue), array('and', 'or'))) {
            $suffix = '__';
        }
        if (!is_array($params)) {
            $result['fields'] = $this->quote($params);
            return $result;
        }
        if (is_array($params)) {
            $result['fields'] = '';
            foreach ($params as $fields => $value) {
                $result['fields'] .= $split . "'$fields' =  :{$suffix}$fields";
                $split = ' ' . $glue . ' ';
                $result['params'][":{$suffix}$fields"] = is_null($value) ? '' : $this->quote($value);
            }
        }
        return $result;
    }

    
    public function getTableColumns($table, $typeOnly = true)
    {
        $result = array();
        $table_temp = $table;

        // Set the query to get the table fields statement.
        $fields = $this->query(
            'SELECT column_name as Field, data_type as Type, is_nullable as \'Null\', column_default as \'Default\'' .
            ' FROM information_schema.columns WHERE table_name = ' . $this->quote($table_temp)
        );

        // If we only want the type as the value add just that to the list.
        if ($typeOnly) {
            foreach ($fields as $field) {
                $result[$field->Field] = preg_replace("/[(0-9)]/", '', $field->Type);
            }
        } // If we want the whole field data object add that to the list.
        else {
            foreach ($fields as $field) {
                $result[$field->Field] = $field;
            }
        }
        return $result;
    }


    public function run($sql, $stuff = 'ims_')
    {
        if (!isset($sql) || empty($sql)) return;

        $ret = array();
        $num = 0;
        foreach (explode(";\n", trim($sql)) as $query) {
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            foreach ($queries as $query) {
                $ret[$num] .= (isset($query[0]) && $query[0] == '#') || (isset($query[1]) && isset($query[1]) && $query[0] . $query[1] == '--') ? '' : $query;
            }
            $num++;
        }
        unset($sql);
        foreach ($ret as $query) {
            $query = trim($query);
            if ($query) {
                $this->query($query);
            }
        }
    }

    public function escape($text, $extra = false)
    {
        $result = addslashes($text);
        $result = str_replace("\'", "''", $result);
        $result = str_replace('\"', '"', $result);
        $result = str_replace('\/', '/', $result);

        if ($extra) {
            // We need the below str_replace since the search in sql server doesn't recognize _ character.
            $result = str_replace('_', '[_]', $result);
        }
        return $result;
    }


    public function fieldexists($tablename, $fieldname)
    {
        $isexists = $this->fetch("DESCRIBE " . $this->tablename($tablename) . " '{$fieldname}'");
        return !empty($isexists) ? true : false;
        //'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = ' . $query->quote($tableName)
    }


    public function indexexists($tablename, $indexname)
    {
        if (!empty($indexname)) {
            $indexs = pdo_fetchall("SHOW INDEX FROM " . $this->tablename($tablename));
            if (!empty($indexs) && is_array($indexs)) {
                foreach ($indexs as $row) {
                    if ($row['Key_name'] == $indexname) {
                        return true;
                    }
                }
            }
        }
        return false;
    }


    public function debug($output = true, $append = array())
    {
        if (!empty($append)) {
            $output = false;
            array_push($this->errors, $append);
        }
        return $this->errors;
    }


    public function tablename($table)
    {
        return $table;
    }
}
