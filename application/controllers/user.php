<?php

class User_Controller extends Base_Controller
{
    public $restful = true;

    public $roles = array(
        'user' => 'Kullanıcı',
        'admin' => 'Yönetici',
    );

    public function __construct()
    {
        parent::__construct();
        $this->filter('before', 'auth:admin');
    }

    public function get_index()
    {
        $per_page = 10;
        $default_sort = array(
            'sort' => 'username',
            'dir' => 'asc',
        );
        $sort = Input::get('sort', $default_sort['sort']);
        $dir = Input::get('dir', $default_sort['dir']);
        $username = Input::get('username');

        $query = DB::table('users');
        if (Auth::user()->id != 1) {
            $query->where('id', '!=', 1);
        }
        $query->order_by($sort, $dir);
        if ($username) {
            $query->where('username', 'LIKE', "%$username%");
        }
        $query = $query->paginate($per_page);
        $users = PaginatorSorter::make($query->results, $query->total, $per_page, $default_sort);
        $this->layout->nest('content', 'user.index', array(
            'users' => $users,
            'roles' => $this->roles,
            'title' => 'Kullanıcı Listesi',
        ));
    }

    public function get_create()
    {
        $user = new User();
        $user->fill(Input::old());
        if (!Input::old()) {
            $user->buttons_downloads = 1;
            $user->buttons_listen = 1;
        }

        $this->layout->title = 'Kullanıcı Kaydı Oluştur';
        $this->layout->nest('content', 'user.form', array(
            'user' => $user,
            'roles' => $this->roles,
        ));
    }

    public function post_create()
    {
        $input = Input::all();
        $rules = User::rules();
        $validation = Validator::make($input, $rules);

        if ($validation->fails()) {
            return Redirect::to('user/create')
                ->with_errors($validation)
                ->with_input();
        } else {
            $user = new User(Input::all());
            $user->password = Hash::make($user->password);
            $user->save();
            return Redirect::to('user/index')
                ->with('message', 'Kullanıcı kaydı eklendi: [' . $user->username . ']')
                ->with('message_status', 'info');
        }
    }

    public function get_update($id)
    {
        $user = User::find($id);
        $user->fill(Input::old());

        $this->layout->title = 'Kullanıcı Kaydı Güncelle';
        $this->layout->nest('content', 'user.form', array(
            'user' => $user,
            'roles' => $this->roles,
        ));
    }

    public function post_update($id)
    {
        $input = Input::all();
        $rules = User::rules();
        $rules['username'] .= ",username,$id";
        unset($rules['password']);
        $validation = Validator::make($input, $rules);

        if ($validation->fails()) {
            return Redirect::to('user/update/' . $id)
                ->with_errors($validation)
                ->with_input();
        } else {
            $user = User::find($id);
            if (empty($input['password'])) {
                unset($input['password']);
            } else {
                $input['password'] = Hash::make($input['password']);
            }
            $user->fill($input);
            $user->save();
            return Redirect::to('user/index')
                ->with('message', 'Kullanıcı bilgileri güncellendi: [' . $user->username . ']')
                ->with('message_status', 'info');
        }
    }

    public function get_delete($id)
    {
        $user = User::find($id);
        $user->delete();
        return Redirect::to('user/index')
            ->with('message', 'Kullanıcı sistemden silindi: [' . $user->username . ']')
            ->with('message_status', 'info');
    }

    public static function roles()
    {
        $roles = array(
            'user' => 'Kullanıcı',
            'admin' => 'Yönetici',
        );
        return $roles;
    }

    public function get_user_auth_log()
    {
        $per_page = 50;

        $logs = DB::table('user_auth_log')
            ->join('users', 'user_auth_log.user_id', '=', 'users.id')
            ->order_by('user_auth_log.id', 'desc')
            ->paginate($per_page);

        $logs = PaginatorSorter::make($logs->results, $logs->total, $per_page, array('id', 'desc'));

        $this->layout->nest('content', 'user.authlog', array(
            'logs' => $logs,
            'title' => 'Kullanıcı Giriş Logları',
        ));
    }
}
