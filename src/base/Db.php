<?php
namespace Rbac\base;

class Db
{

    private $conn;
    private $config;
    private $user = 'qx_user';//用户表
    private $role = 'qx_role';//角色表
    private $node = 'qx_node';//节点表
    private $user_role = 'qx_user_role';//用户角色表
    private $access = 'qx_access';//用户及角色权限表
    private $opt = array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8");

    public function setInstance($config)
    {
        $this->config = $config;
    }

    public function getInstance()
    {
        try {
            if ($this->conn == null) {
                $dsn = $this->config['driver'] . ':host=' . $this->config['host'] . ';dbname=' . $this->config['database'].';port:'.$this->config['port'];
                $this->conn = new \PDO($dsn, $this->config['username'], $this->config['password'],$this->opt);
            }
        }catch (\PDOException $e){
            echo $e->getMessage();
        }
        return $this->conn;
    }

    public function setTable($tables)
    {                
        if (is_array($tables)) {            
            foreach ($tables as $k => $v) {
                $this->$k = $this->config['prefix'].$v;
            }
        }
    }

    public function getPrefix(){
        return $this->config['prefix'];
    }


    public function getTableName($name)
    {
        return $this->config['prefix'].$this->$name;
    }
}
