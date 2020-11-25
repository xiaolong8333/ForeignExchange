<?php

namespace server;

class Database
{
    private $mysqli;
    private $name;
    static public $instance;
    public $mysqlMes=[
        'db_host'=>'192.168.0.150',
        'db_user'=>'root',
        'db_pwd'=>'123456',
        'db_name'=>'foreign_currency'
    ];
    private function __construct()
    {
        $this->mysqli = new \mysqli($this->mysqlMes['db_host'], $this->mysqlMes['db_user'], $this->mysqlMes['db_pwd'], $this->mysqlMes['db_name']);
        if(mysqli_connect_error()){
            exit("连接数据库失败！");
        }
        $this->mysqli->set_charset("utf8");//或者 $mysqli->query("set names 'utf8'")
    }
    /**
     *单例模式
     */
    static public function getInstance()
    {//声明一个getinstance()静态方法，用于检测是否有实例对象

        if(!self::$instance) self::$instance = new self();

        return self::$instance;

    }
    /**
     *@desc执行sql语句
     */
    public function querySql(string $sql)
    {

        $result = $this->mysqli->query($sql);
        $data = [];
        if (!$result) {
            $this->re_connect();
            $result = $this->mysqli->query($sql);
        }
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            //移动指针到下一条sql
            $this->mysqli->next_result();
        }
        return $data;
    }
    public function __call(string $name,array $args=[])
    {
        if($args==[]){
            $sql = "call {$name} ()";
        }
        else{
            $args = implode(',',array_map(array($this,'escape'), $args));
            $sql = "call {$name} ({$args})";
        }
        return $this->querySql($sql);
    }
    private function re_connect()
    {
        return $this->mysqli->connect($this->mysqlMes['db_host'], $this->mysqlMes['db_user'], $this->mysqlMes['db_pwd'], $this->mysqlMes['db_name']);
        $this->mysqli->refresh(MYSQLI_REFRESH_HOSTS);
    }
    private function __close()
    {
        $this->mysqli->close();
    }
    public function escape(string $value){
        return "'".(string)$value."'";
    }

}

