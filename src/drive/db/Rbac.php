<?php
namespace Rbac\drive\db;

use Rbac\base\Db;
use Rbac\interfaces\RbacApi;

class Rbac extends Db implements RbacApi
{

    private $result_type = \PDO::FETCH_ASSOC;

    function __construct($config)
    {
        $this->setInstance($config);
    }

    /**
     * desc : 权限验证
     *
     * @param $uid
     * @param $uri
     * @param bool $only_fun
     * @return bool
     */
    public function userAuth($uid, $uri, $only_fun = false)
    {
        $node_id = $this->getNodeId($uri);
        if($node_id && $only_fun){
            $nodes = $this->getNodeInfo($node_id);
            if($nodes['menu'] == 1){
                return true;
            }
        }
        if($node_id){
            $nodes = $this->getAllNodes($uid);
            if (in_array($node_id[0]['id'], $nodes)) {
                return true;
            }
        }
        return false;
    }

    /*-----------------------Role User-----------------------*/
    /**
     * 新增角色
     */
    public function addRole($params){
        $db = $this->getInstance();

        $role_table = $this->getTableName('role');
        $sql = <<<EOT
            insert into {$role_table}
                  (`name`, `pid`, `status`,`create_time`,`update_time`)
                  VALUES (  '{$params['name']}',
                            '{$params['pid']}',
                            '{$params['status']}',
                            now(),now()
                         )
EOT;
        $add_res = $db->exec($sql);
        return $add_res;
    }

    /**
     * 更新角色
     */
    public function updateRole($params){
        $db = $this->getInstance();
        $able_fields = ['name', 'pid', 'status'];
        $condition_str = $this->getUpdateStr($params, $able_fields);
        $role_table = $this->getTableName('role');
        $sql = <<<EOT
            update {$role_table} set {$condition_str} where id = {$params['id']}
EOT;
        $add_res = $db->exec($sql);
        return $add_res;
    }

    /**
     * 角色列表
     *
     * @param bool $all
     * @return array
     */
    public function getRoles($all = false,$role_id = ''){
        $db = $this->getInstance();
        $qx_role = $this->getTableName('role');
        $condition = '';
        if($all){
            $condition = 'where status = 1';
        }

        if(!empty($role_id)){
            if(empty($condition)){
                $condition .= " where id = {$role_id} ";
            }else{
                $condition .= " AND id = {$role_id} ";
            }
        }

        $sql = <<<EOT
        select * from {$qx_role} {$condition};
EOT;
        $list = $db->query($sql)->fetchAll($this->result_type);
        return $list;
    }

    /**
     * 获取用户角色
     *
     * @param string $uid
     * @param bool $combine
     * @param bool $self
     * @param int $offset
     * @param int $page_size
     * @return array
     */
    public function getUserRoles($uid='', $combine = false, $self = true, $offset=0, $page_size=0){
        return $this->getSqlUsers($uid,$combine,$self,$offset,$page_size);
    }

    private function getSqlUsers($uid='',$combine = false,$self = true,$offset,$page_size){
        $db = $this->getInstance();
        $condition = empty($uid) ? '' : "AND bb.user_id IN ({$uid})";
        $qx_user_role = $this->getTableName('user_role');
        $qx_role = $this->getTableName('role');

        $limit = '';
        if(!empty($page_size)){
            $limit = "  Limit {$offset},{$page_size}";
        }
        $sql = <<<EOT
        select cc.*,bb.user_id from {$qx_user_role} bb

        join {$qx_role} cc ON cc.id = bb.role_id

        where cc.status = 1 $condition {$limit};
EOT;
        $user = $db->query($sql)->fetchAll($this->result_type);
        if(!empty($combine) ){
            $user = $this->combineUser($user);
            return $user;
        }

        $sql = <<<EOT
        select cc.* from {$qx_role} cc where cc.status = 1;
EOT;
        $all_roles = $db->query($sql)->fetchAll($this->result_type);
        $users_role_ids = array_column($user,'id');
        $new_all_roles = [];
        foreach($all_roles as &$all_role){
            $all_role['check'] = 0;
            if(in_array($all_role['id'],$users_role_ids)){
                $all_role['check'] = 1;
                if($self) $new_all_roles[] = $all_role;
            }
            if(!$self){
                $new_all_roles[] = $all_role;
            }
        }
        return $new_all_roles;
    }

    private function combineUser($users){
        $arr = [];
        if(!empty($users)){
            foreach($users as $user){
                if(in_array($user['user_id'],array_keys($arr))){
                    $arr[$user['user_id']]['name'] =  $arr[$user['user_id']]['name'] .','. $user['name'];
                }else{
                    $arr[$user['user_id']] = $user;
                }
            }
        }

        return $arr;
    }

    /**
     * desc : 批量更新角色权限（角色权限）
     * author : luoqi
     * time : 2017/3/9 16:20
     * param $role_id
     * param $uids data
     * return array
     */
    public function editRolesUser($role_id, $uids)
    {

        $del = $values ='';
        $new_add = [];
        foreach ($uids as $k => $v) {
            array_push($uids, $v[0]);
            if ($v[1] == 'true') {
                array_push($new_add,$v[0]);
            } else {
                $del .= $v[0].',';
            }
        }

        $db = $this->getInstance();
        $user_role = $this->getTableName('user_role');
        $user_ids = $this->UsersExistRole($role_id,$new_add);
        if($user_ids){
            $user_ids = $this->getValues($user_ids,'user_id');
        }
        $add_count = $del_count = 0;
        $add = array_diff($new_add, $user_ids);
        if ($add) {
            foreach ($add as $k=>$v){
                $values .= "($role_id,$v),";
            }
            $sql = 'insert into '.$user_role.' (role_id,user_id) values'.rtrim($values,',');
            $add_count = $db->exec($sql);
        }

        //删除
        if ($del) {
            $sql = 'delete from '.$user_role.' where role_id='.$role_id.' and user_id in ('.rtrim($del,',').')';
            $del_count = $db->exec($sql);
        }

        return ['add' => $add_count, 'del' => $del_count];

    }

    /*-----------------------NODE-----------------------*/
    /**
     * 根据id获取菜单节点信息
     *
     * @param $id
     * @return array
     */
    public function getNodeById($id){
        $db = $this->getInstance();
        $node_table = $this->getTableName('node');
        $sql = <<<EOT
        select * from {$node_table} where id = {$id} AND status = 1
EOT;
        $res =  $db->query($sql)->fetchAll($this->result_type);;
        return $res;
    }

    /**
     * 获取一维节点,切片
     *
     * @param $params
     * @return array|bool
     */
    public function getNodeList($params){
        $db = $this->getInstance();
        $node_table = $this->getTableName('node');
        if(empty($params['page_size'] || empty($params['offset']))){
            return false;
        }
        $type_str = '';
        if($params['type'] == 1){ // 菜单列表
            $type_str = ' AND menu = 1';
        }elseif($params['type'] == 2){ // 节点列表
            $type_str = ' AND menu = 0';
        }
        $mode_str = '';
        if(!empty($params['mode'])){
            $mode_str = ' AND ';
            foreach($params['mode'] as $key => $val){
                $mode_str .= $key . ' like ' . "'%{$val}%'";
            }
        }
        $sql = <<<EOT
        select * from {$node_table} where status = 1 {$type_str} {$mode_str}  Limit {$params['offset']},{$params['page_size']}
EOT;
        $res =  $db->query($sql)->fetchAll($this->result_type);
        $count_sql = <<<EOT
        select count(*) as 'count' from {$node_table} where status = 1 {$type_str}
EOT;
        $count =  $db->query($count_sql)->fetchAll($this->result_type);
        return [
            'data'  => $res,
            'count' =>  $count[0]['count']
        ];
    }

    /**
     * desc : 用户所有权限
     * author : luoqi
     * time : 2017/03/21 14:42
     * @param $uid int
     * @return array
     */
    public function getAllNodes($uid)
    {
        $Role = $this->getRoleNode($uid);
        $User = $this->getUserNode($uid);
        $nodes = array_merge((array)$Role, (array)$User);
        return array_filter($nodes);
    }

    //根据用户角色 获取用户节点 （角色权限）,
    public function getRoleNode($uid)
    {
        $db = $this->getInstance();
        $user_role = $this->getTableName('user_role');
        $access = $this->getTableName('access');
        $sql = 'select node_id from '.$user_role.' r left join '.$access.' a on r.role_id = a.role_id where r.user_id='.$uid;
        $node_ids = $db->query($sql)->fetchAll($this->result_type);
        if(empty($node_ids)){
            return $node_ids;
        }
        return $this->getValues($node_ids,'node_id');
    }

    //根据角色获取角色节点 （角色权限）
    public function getRoleNodes($role_id)
    {
        $db = $this->getInstance();
        $node = $this->getTableName('node');
        $access = $this->getTableName('access');
        $sql = 'select node_id from '.$node.' n inner join '.$access.' a on n.id=a.node_id where a.role_id='.$role_id;
        $node_ids = $db->query($sql)->fetchAll($this->result_type);
        return $node_ids;
    }

    //根据用户ID 获取用户节点 （个人权限）
    public function getUserNode($uid)
    {
        $db = $this->getInstance();
        $access = $this->getTableName('access');
        $sql = 'select node_id from '.$access.' where user_id='.$uid;
        $node_ids = $db->query($sql)->fetchAll($this->result_type);
        if(empty($node_ids)){
            return $node_ids;
        }
        return $this->getValues($node_ids,'node_id');
    }

    //根据用户ID 获取用户角色 （角色权限）
    public function getUserRoless($uid)
    {
        $db = $this->getInstance();
        $user_role = $this->getTableName('user_role');
        $sql = 'select role_id from '.$user_role.' where user_id='.$uid;
        $role_ids = $db->query($sql)->fetchAll($this->result_type);
        return $this->getValues($role_ids,'role_id');
    }

    /**
     * 通过路由获取节点ID
     * @param $uri
     * @return node_id
     */
    public function getNodeId($uri)
    {
        $db = $this->getInstance();
        $node= $this->getTableName('node');
        $sql = 'select id from '.$node." where name='$uri' and status=1 limit 1";
        $node_id = $db->query($sql)->fetchAll($this->result_type);
        return $node_id;
    }

    /**
     * 获取节点信息
     *
     * @param $node_id
     * @return array|string
     */
    public function getNodeInfo($node_id){
        $db = $this->getInstance();
        $node= $this->getTableName('node');
        $sql = "select * from {$node} where id = {$node_id}";
        $node = $db->query($sql)->fetchAll($this->result_type);
        return $node;
    }

    /**
     * 获取用户菜单
     *
     * @param $uid
     * @param bool $visibility true 只获取展示出来的
     * @return array|string
     */
    public function getUserMenu($uid,$visibility)
    {
        $db = $this->getInstance();
        $nodes = $this->getAllNodes($uid);
        $node = $this->getTableName('node');
        if($visibility){
            $sql = 'select * from '.$node.' where status=1 and menu=1 and id in('.implode(',',$nodes).')'.' AND visibility = 1';
        }else{
            $sql = 'select * from '.$node.' where status=1 and menu=1 and id in('.implode(',',$nodes).')';
        }
        $list = $db->query($sql)->fetchAll($this->result_type);
        if(empty($list)){
            return $list;
        }
        $node = self::getTree($list, 0);
        return $node;
    }

    /**
     * 获取用户菜单
     *
     * @param bool $visibility true 只返回展示的菜单
     * @return array|string
     */
    public function getAllMenu($visibility=true)
    {
        $db = $this->getInstance();
        $node = $this->getTableName('node');
        if($visibility){
            $sql = 'select * from '.$node.' where status=1 and menu=1 AND visibility = 1';
        }else{
            $sql = 'select * from '.$node.' where status=1 and menu=1';
        }
        $list = $db->query($sql)->fetchAll($this->result_type);
        if(empty($list)){
            return $list;
        }
        $node = self::getTree($list, 0);
        return $node;
    }

    /**
     * 所有节点，并选中用户拥有的节点
     *
     * @param $uid
     * @param bool $self check=0时是否返回
     * @param bool $tree 是否返回树形结构
     * @return array|string
     */
    public function AllToMe($uid,$self = false,$tree=true)
    {
        $db = $this->getInstance();
        $nodes = $this->getAllNodes($uid);
        $node = $this->getTableName('node');
        $sql = 'select id,title,pid,name,sort,icon,menu from ' . $node . ' where status = 1';
        $list = $db->query($sql)->fetchAll($this->result_type);
        if(empty($list)) return $list;
        foreach ($list as $k => $v) {
            $list[$k]['check'] = 0;
            if (in_array($v['id'], $nodes)) {
                $list[$k]['check'] = 1;
            }elseif($self){
                unset($list[$k]);
            }
        }
        if($tree) return self::getTree($list, 0);
        return $list;
    }

    /**
     * 用户本身拥有的权限
     *
     * @param $uid
     * @param bool $self
     * @return array|string
     */
    public function OnlyToMe($uid,$self=false){
        $db = $this->getInstance();
        $nodes = $this->getUserNode($uid);
        $node = $this->getTableName('node');
        $sql = 'select id,title,pid,name,sort,icon,menu from '.$node;
        $list = $db->query($sql)->fetchAll($this->result_type);
        if(empty($list)){
            return $list;
        }
        foreach ($list as $k => $v) {
            $list[$k]['check'] = 0;
            if (in_array($v['id'], $nodes)) {
                $list[$k]['check'] = 1;
            }elseif($self){
                unset($list[$k]);
            }
        }

        $node = self::getTree($list, 0);
        return $node;
    }

    /**
     * desc : 所有节点，并选中角色拥有的节点
     * author : luoqi
     * time : 2017/05/10 17:28
     * param $uid int
     * @return array
     */
    public function AllToRoles($role_id,$self = false)
    {
        $db = $this->getInstance();
        $nodes = $this->getRoleNodes($role_id);
        $node = $this->getTableName('node');
        $sql = 'select id,name,sort,title,pid,icon,menu,visibility from '.$node;
        $list = $db->query($sql)->fetchAll($this->result_type);
        if(empty($list)){
            return $list;
        }
        $nodes = array_column($nodes,'node_id');
        foreach ($list as $k => $v) {
            $list[$k]['check'] = 0;
            if (in_array($v['id'], $nodes)) {
                $list[$k]['check'] = 1;
            }elseif($self){
                unset($list[$k]);
            }
        }

        $node = self::getTree($list, 0);
        return $node;
    }


    /**
     * desc : 批量更新用户权限（个人权限）
     * auth : luoqi
     * time : 2017/03/21 14:32
     * param $uid int
     * param $node_ids array
     * return array
     */
    public function editNodes($uid, $node_ids)
    {
        $add_res = $del_res = 0;
        if ($node_ids) {
            $db = $this->getInstance();
            $access = $this->getTableName('access');

            $nodes = $this->getAllNodes($uid);
            //新增
            $add = array_diff($node_ids, $nodes);
            try {
                if ($add) {
                    $values = '';
                    foreach ($add as $key => $value) {
                        $values .= "($value,$uid),";
                    }
                    $sql = 'insert into ' . $access . '(node_id,user_id) values' . rtrim($values,',');
                    $add_res = $db->exec($sql);
                }

                //删除
                $del = array_diff($nodes, $node_ids);
                if ($del) {
                    $sql = 'delete from ' . $access . ' where user_id=' . $uid . ' and node_id in(' . implode(',',$del) . ')';
                    $del_res = $db->exec($sql);
                }
            }catch (\PDOException $e){
                echo $e->getMessage();
            }

            return ['add' => (int)$add_res, 'del' => (int)$del_res];
        }
    }

    /**
     * desc : 批量给角色分配权限（角色权限）
     * auth : luoqi
     * time : 2017/05/10 16:51
     * param $uid int
     * param $node_ids array
     * return array
     */
    public function editRolesNode($role_id,$node_ids){
        $add_res = $del_res = 0;
        if ($node_ids !== false) {
            $db = $this->getInstance();
            $access = $this->getTableName('access');

            $nodes = array_column($this->getRoleNodes($role_id),'node_id');
            //新增
            $add = array_diff($node_ids, $nodes);
            try {
                if ($add) {
                    $values = '';
                    foreach ($add as $key => $value) {
                        $values .= "($value,$role_id),";
                    }
                    $sql = 'insert into ' . $access . '(node_id,role_id) values' . rtrim($values,',');
                    $add_res = $db->exec($sql);
                }

                //删除
                $del = array_diff($nodes, $node_ids);
                if ($del) {
                    $sql = 'delete from ' . $access . ' where role_id=' . $role_id . ' and node_id in(' . implode(',',$del) . ')';
                    $del_res = $db->exec($sql);
                }
            }catch (\PDOException $e){
                echo $e->getMessage();
            }

            return ['add' => (int)$add_res, 'del' => (int)$del_res];
        }

    }

    public function addNode($params){
        $db = $this->getInstance();

        $node_table = $this->getTableName('node');
        $sql = <<<EOT
            insert into {$node_table}
                  (`name`, `title`, `status`, `sort`, `pid`, `icon`, `menu`, `visibility`, `create_time`)
                  VALUES (  '{$params['name']}',
                            '{$params['title']}',
                            '{$params['status']}',
                            '{$params['sort']}',
                            '{$params['pid']}',
                            '{$params['icon']}',
                            '{$params['menu']}',
                            '{$params['visibility']}',
                            now()
                         )
EOT;

        $add_res = $db->exec($sql);
        return $add_res;
    }

    public function updateNode($params){
        $db = $this->getInstance();
        $able_fields = ['name', 'title', 'status', 'sort', 'visibility', 'pid', 'icon', 'menu'];
        $condition_str = $this->getUpdateStr($params, $able_fields);
        $node_table = $this->getTableName('node');
        $sql = <<<EOT
            update {$node_table} set {$condition_str} where id = {$params['id']}
EOT;
        $add_res = $db->exec($sql);
        return $add_res;
    }

    public function delNode($params){
        $db = $this->getInstance();
        $node_table = $this->getTableName('node');
        $access_table = $this->getTableName('access');
        //$node_ids = implode(',',$params['id']);
        $sql = <<<EOT
        delete from {$node_table} where id = {$params['id']}
EOT;
        $del_res = $db->exec($sql);
        if($del_res === false) return false;
        $relation_sql = <<<EOT
        delete from {$access_table} where node_id = {$params['id']}
EOT;
        $del_res = $db->exec($relation_sql);
        return $del_res;
    }

    private function getUpdateStr($condition_arr, $able_fields = '*'){
        $condition_str = '';
        foreach($condition_arr as $key => $val){
            if($able_fields == '*' || in_array($key,$able_fields)){
                if(is_numeric($val)){
                    $condition_str = $condition_str . $key . '=' . "{$val}".',';
                }else{
                    $condition_str = $condition_str . $key . '=' . "'{$val}'".',';
                }
            }
        }

        return rtrim($condition_str,',');
    }

    /*-----------------------ROLE-----------------------*/


    /**
     * desc : 获取角色的所有用户
     * author : luoqi
     * time : 2017/3/9 16:20
     * param $role_id
     * return array
     */
    public function RoleToUsers($role_id)
    {
        $db = $this->getInstance();
        $qx_access = $this->getTableName('user_role');
        $sql = 'select * from '.$qx_access.' where role_id='.$role_id;
        $list = $db->query($sql)->fetchAll($this->result_type);
        return $list;
    }

    //查看UID是否拥有当前角色
    public function UsersExistRole($role_id,$uids)
    {
        $db = $this->getInstance();
        $qx_access = $this->getTableName('user_role');
        $sql = 'select user_id from '.$qx_access.' where role_id='.$role_id.' and user_id in('.implode(',',$uids).')';
        $list = $db->query($sql)->fetchAll($this->result_type);
        return $list;
    }


    /**
     * desc : 根据用户批量更新角色权限
     * author : luoqi
     * time : 2017/5/12 17:29
     * param $uid
     * param $role_ids
     * return array
     */
    public function editUserRoles($uid,$role_ids){
        $add_res = $del_res = 0;
        if ($role_ids !== false) {
            $db = $this->getInstance();
            $user_role = $this->getTableName('user_role');
            $role_id = $this->getUserRoless($uid);
            //新增
            $add = array_diff($role_ids, $role_id);
            try {
                if ($add) {
                    $values = '';
                    foreach ($add as $key => $value) {
                        $values .= "($value,$uid),";
                    }
                    $sql = 'insert into ' . $user_role . '(role_id,user_id) values' . rtrim($values,',');
                    $add_res = $db->exec($sql);
                }

                //删除
                $del = array_diff($role_id, $role_ids);
                if ($del) {
                    $sql = 'delete from ' . $user_role . ' where user_id=' . $uid . ' and role_id in(' . implode(',',$del) . ')';
                    $del_res = $db->exec($sql);
                }
            }catch (\PDOException $e){
                echo $e->getMessage();
            }

            return ['add' => (int)$add_res, 'del' => (int)$del_res];
        }
    }

    /*-----------------------工具方法-----------------------*/

    //生成节点树
    public static function getTree($data, $pid)
    {
        $tree = [];
        foreach ($data as $key => $value) {
            if ($value['pid'] == $pid) {
                $list = self::getTree($data, $value['id']);
                if ($list) {
                    $value['list'] = $list;
                }
                $tree[] = $value;
                unset($data[$key]);
            }
        }
        return $tree;
    }

    public function getValues($arr,$key){
        $arrs = [];
        foreach ($arr as $k=>$v){
            $arrs[] = $v[$key];
        }
        unset($arr);
        return $arrs;
    }

}
