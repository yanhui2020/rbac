<?php
namespace Rbac\interfaces;

interface RbacApi{
    public function getAllNodes($uid);//用户所有权限
    public function getRoleNode($uid);//根据用户角色 获取用户节点 （角色权限）

    public function getRoleNodes($role_id);//根据角色获取角色节点 （角色权限）
    public function editRolesNode($role_id,$node_ids);//批量给角色分配权限（角色权限）
    public function editUserRoles($uid,$role_ids);//根据用户批量更新（角色权限）
    public function AllToRoles($role_id);//获取所有节点，并选择角色拥有的节点
    public function getUserNode($uid);//根据用户ID 获取用户节点 （个人权限）
    public function userAuth($uid, $uri, $only_fun);//权限验证
    public function editNodes($uid, $node_ids);//批量更新用户权限（个人权限）
    public function editRolesUser($role_id, $uids);//批量更新角色权限（角色权限）
    public function getUserMenu($uid,$visibility);//获取用户菜单
    public function AllToMe($uid,$type);//所有节点，并选中用户拥有的节点


    /*-------- 角色 -----------*/
    public function RoleToUsers($role_id);//获取角色的所有用户
    public function addRole($params);   //新增角色
    public function updateRole($params);       //更新角色

    /*-------- 节点 -----------*/
    public function addNode($params);       //新增节点
    public function updateNode($params);    //更新权限
}