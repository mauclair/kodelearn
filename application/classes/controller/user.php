<?php defined('SYSPATH') or die('No direct script access.');

class Controller_User extends Controller_Base {
	
	public function action_index() {
		
		
        if($this->request->param('sort')){
            $sort = $this->request->param('sort');
        } else {
            $sort = 'id';
        }
        
        if($this->request->param('order')){
            $order = $this->request->param('order');
        } else {
            $order = 'DESC';
        }
		
        $user = ORM::factory('user');

        if($this->request->param('filter_name')){
            $user->where('users.firstname', 'LIKE', '%' . $this->request->param('filter_name') . '%');
        }
        
        if($this->request->param('filter_id')){
            $user->where('users.id', '=', (int) $this->request->param('filter_id') );
        }
        
        $count = $user->count_all();
        
        $pagination = Pagination::factory(array(
            'total_items'    => $count,
            'items_per_page' => 5,
        ));
        
        if($this->request->param('filter_name')){
            $user->where('users.firstname', 'LIKE', '%' . $this->request->param('filter_name') . '%');
        }
        
        if($this->request->param('filter_id')){
            $user->where('users.id', '=', (int) $this->request->param('filter_id') );
        }
        
        $users = $user->order_by($sort, $order)
                ->limit($pagination->items_per_page)
                ->offset($pagination->offset)
                ->find_all();
        
        $sorting = new Sort(array(
                'Roll No'           => 'id',
                'Name'              => 'firstname',
                'Batch'             => '',
                'Cources'           => '',
                'Actions'           => ''
        ));
        
        $url = ('user/index');
        
        if($this->request->param('filter_name')){
            $url .= '/filter_name/'.$this->request->param('filter_name');
        }
        
        if($this->request->param('filter_id')){
            $url .= '/filter_id/'.$this->request->param('filter_id');
        }
        
        $sorting->set_link($url);
        
        $sorting->set_order($order);
        $sorting->set_sort($sort);
        $heading = $sorting->render();
        
        $pagination = $pagination->render();
        
        $links = array(
            'add' => Html::anchor('/user/add/', 'Create a user', array('class' => 'createButton l')),
            'delete'      => URL::site('/user/delete/')
        );
                
        $table['heading'] = $heading;
        $table['data'] = $users;
        
        $filter_name = $this->request->param('filter_name');
        $filter_url = URL::site('user/index');
        $cacheimage = new CacheImage();
        
        
        $view = View::factory('user/list')
                  ->bind('table', $table)
                  ->bind('users', $users)
                  ->bind('links', $links)
                  ->bind('pagination', $pagination)
                  ->bind('filter_name', $filter_name)
                  ->bind('filter_id', $filter_id)
                  ->bind('filter_url', $filter_url)
                  ->bind('cacheimage', $cacheimage)
                  ;
		
		$this->content = $view;
	}
	
	public function action_add(){
         $submitted = false;
         
         if($this->request->method() === 'POST' && $this->request->post()){
            if (Arr::get($this->request->post(), 'save') !== null){
                $submitted = true;
                $user = ORM::factory('user');
                $validator = $user->validator_create($this->request->post());
                $validator->bind(':user', NULL);
                if ($validator->check()) {

                	$user->firstname = $this->request->post('firstname');
                    $user->lastname = $this->request->post('lastname');
                    $user->email = $this->request->post('email');
                    $user->password = Auth::instance()->hash(rand(10000, 65000));
                    $role = ORM::factory('role', $this->request->post('role_id'));
                    $user->save();
                    $user->add('roles', $role);
                   
                    foreach($this->request->post('batch_id') as $batch_id){
                        $batch = ORM::factory('batch', $batch_id);
                        $user->add('batches', $batch);
                    }
                    Request::current()->redirect('user');
                    exit;
                } else {
                    $this->_errors = $validator->errors('register');
                }
            }
         }
		
		$form = $this->form('user/add', $submitted);
		
        $view = View::factory('user/form')
                    ->bind('form', $form);
        $this->content = $view;
	}
	
	private function form($action, $submitted = false, $saved_data = array()) {
		
        $roles = array();
        foreach(ORM::factory('role')->find_all() as $role){
            $roles[$role->id] = $role->name;
        }

        $batches = array();
        foreach(ORM::factory('batch')->find_all() as $batch){
            $batches[$batch->id] = $batch->name;
        }
        
		$form = new Stickyform($action, array(), ($submitted ? $this->_errors : array()));
        $form->default_data = array(
            'firstname' => '',
            'lastname'  => '',
            'email'     => '',
            'role_id'   => '',
            'batch_id'  => ''
        );
        
        $form->saved_data = $saved_data;
        $form->posted_data = $submitted ? $this->request->post() : array();
        $form->append('First Name', 'firstname', 'text');
        $form->append('Last Name', 'lastname', 'text');
        $form->append('Email', 'email', 'text');
        $form->append('Role', 'role_id', 'select', array('options' => $roles));
        $form->append('Select batch', 'batch_id', 'select', array('options' => $batches, 'attributes' => array('multiple' => 'multiple', 'name' => 'batch_id[]')));
        $form->append('Save', 'save', 'submit', array('attributes' => array('class' => 'button')));
        $form->process();
        return $form;
	}
	
	public function action_edit() {
        $submitted = false;
		
		$id = $this->request->param('id');
        if(!$id)
            Request::current()->redirect('user');
            
        $user = ORM::factory('user', $id);

        if($this->request->method() === 'POST' && $this->request->post()){
            if (Arr::get($this->request->post(), 'save') !== null){
                $submitted = true;
                $validator = $user->validator_create($this->request->post());
                $validator->bind(':user', $user);
                if ($validator->check()) {
                    $user->firstname = $this->request->post('firstname');
                    $user->lastname = $this->request->post('lastname');
                    $user->email = $this->request->post('email');
                    //removing the previous role assigned
                    $user->remove('roles');
                    //creating a role object and assigning a new role
                    $role = ORM::factory('role', $this->request->post('role_id'));
                    $user->add('roles', $role);
                    
                    //removing the previous batch assigned
                    $user->remove('batches');
                    
                    foreach($this->request->post('batch_id') as $batch_id){
	                    $batch = ORM::factory('batch', $batch_id);
	                    $user->add('batches', $batch);
                    }
                    
                    $user->save();
                    Request::current()->redirect('user');
                    exit;
                } else {
                    $this->_errors = $validator->errors('register');
                }
            }
         }
        
        $form = $this->form('user/edit/id/'.$id ,$submitted, array('firstname' => $user->firstname, 'lastname' => $user->lastname, 'email' => $user->email, 'role_id' => $user->roles->find()->id, 'batch_id' => $user->batches->find_all()->as_array(NULL, 'id')));
        
        
        $view = View::factory('user/form')
                  ->bind('form', $form);
        $this->content = $view;
	}

	public function action_delete(){
        if($this->request->method() === 'POST' && $this->request->post('selected')){
            foreach($this->request->post('selected') as $user_id){
                ORM::factory('user', $user_id)->delete();
            }
        }
        Request::current()->redirect('user');
    }
}
