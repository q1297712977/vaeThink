<?php
namespace app\admin\controller;
use vae\controller\AdminCheckAuth;
use think\Db;

class Admin extends AdminCheckAuth
{
    public function index()
    {
        return view();
    }

    //管理员列表
    public function getAdminList()
    {
    	$param = vae_get_param();
        $where = array();
        if(!empty($param['keywords'])) {
            $where['id|username|nickname|desc|phone'] = ['like', '%' . $param['keywords'] . '%'];
        }
        $rows = empty($param['limit']) ? \think\Config::get('paginate.list_rows') : $param['limit'];
        $admin = \think\loader::model('Admin')
    			->order('create_time asc')
                ->where($where)
    			->paginate($rows,false,['query'=>$param])
    			->each(function($item, $key){
		            $item->last_login_time = date('Y-m-d H:i:s',$item->last_login_time);
		            $groupId = Db::name('AdminGroupAccess')->where(['uid'=>$item->id])->column('group_id');
		            $groupName = Db::name('AdminGroup')->where(['id'=>['IN',$groupId]])->column('title');
		            $item->groupName = implode(',',$groupName);
		        });
    	return vae_assign_table(0,'',$admin);
    }

    //添加
    public function add()
    {
    	return view();
    }

    //提交添加
    public function addSubmit()
    {
    	if($this->request->isPost()){
    		$param = vae_get_param();
    		$result = $this->validate($param, 'app\admin\validate\Admin.add');
            if ($result !== true) {
                return vae_assign(0,$result);
            } else {
            	$param['salt'] = vae_set_salt(20);
            	$param['password'] = vae_set_password($param['password'],$param['salt']);
				// 启动事务
				Db::startTrans();
				try{
				    $uid = \think\loader::model('Admin')->strict(false)->field(true)->insertGetId($param);
                	foreach ($param['group_id'] as $k => $v) {
                		$data[$k] = [
                			'uid' => $uid,
                			'group_id' => $v
                		];
                	}
					\think\loader::model('AdminGroupAccess')->strict(false)->field(true)->insertAll($data);
				    // 提交事务
				    Db::commit();    
				} catch (\Exception $e) {
				    // 回滚事务
				    Db::rollback();
				    return vae_assign(0,'提交失败:'.$e->getMessage());
				}
                return vae_assign();
            }
    	}
    }

    //修改
    public function edit()
    {
        return view('',['admin'=>vae_get_admin(vae_get_param('id'))]);
    }

    //提交修改
    public function editSubmit()
    {
        if($this->request->isPost()){
            $param = vae_get_param();
            $result = $this->validate($param, 'app\admin\validate\Admin.edit');
            if ($result !== true) {
                return vae_assign(0,$result);
            } else {
                if(!empty($param['password'])) {
                    //重置密码
                    if(empty($param['password_confirm']) or $param['password_confirm'] !== $param['password']) {
                        return vae_assign(0,'两次密码不一致');
                    }
                    $param['salt'] = vae_set_salt(20);
                    $param['password'] = vae_set_password($param['password'],$param['salt']);
                } else {
                    unset($param['password']);
                    unset($param['salt']);
                }
                
                // 启动事务
                Db::startTrans();
                try{
                    \think\loader::model('Admin')->where(['id'=>$param['id']])->strict(false)->field(true)->update($param);
                    Db::name('AdminGroupAccess')->where(['uid'=>$param['id']])->delete();
                    foreach ($param['group_id'] as $k => $v) {
                        $data[$k] = [
                            'uid' => $param['id'],
                            'group_id' => $v
                        ];
                    }
                    \think\loader::model('AdminGroupAccess')->strict(false)->field(true)->insertAll($data);
                    // 提交事务
                    Db::commit();    
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return vae_assign(0,'提交失败:'.$e->getMessage());
                }
                return vae_assign();
            }
        }
    }

    //删除
    public function delete()
    {
        $id    = vae_get_param("id");
        if ($id == 1) {
            return vae_assign(0,"系统拥有者，无法删除！");
        }
        if (Db::name('Admin')->delete($id) !== false) {
            return vae_assign(1,"删除管理员成功！");
        } else {
            return vae_assign(0,"删除失败！");
        }
    }
}
