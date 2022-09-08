<?php


use Illuminate\Http\Request;

class Home_Controller extends Base_Controller
{
    /*
    |--------------------------------------------------------------------------
    | The Default Controller
    |--------------------------------------------------------------------------
    |
    | Instead of using RESTful routes and anonymous functions, you might wish
    | to use controllers to organize your application API. You'll love them.
    |
    | This controller responds to URIs beginning with "home", and it also
    | serves as the default controller for the application, meaning it
    | handles requests to the root of the application.
    |
    | You can respond to GET requests to "/home/profile" like so:
    |
    |		public function action_profile()
    |		{
    |			return "This is your profile!";
    |		}
    |
    | Any extra segments are passed to the method as parameters:
    |
    |		public function action_profile($id)
    |		{
    |			return "This is the profile for user {$id}.";
    |		}
    |
    */

    public $restful = true;

    public function get_index()
    {
        if (Auth::check()) {
            return Redirect::to('cdr');
        } else {
            return Redirect::to('login');
        }
    }

    public function get_login()
    {
        $this->layout->title = 'Giriş';
        $this->layout->nest('content', 'home.login');
    }

    public function post_login()
    {
        $input = Input::get();
        $redirect = 'cdr';

        if (Auth::attempt($input)) {
            $insert = DB::table('user_auth_log')->insert(array(
                'user_id'=>Auth::user()->id,
                'timestamp'=>date('Y-m-d H:i:s'),
                'auth_type'=>'IN'
            ));
            $dir = Cdr::getTemporaryOggDir();
            foreach (glob($dir . '/*') as $file) {
                unlink($file);
            }

            //if (isset($input['redirect'])) $redirect = $input['redirect'];
            return Redirect::to($redirect)
                /*->with('message', 'Giriş yapıldı.')
                ->with('message_status', 'success')*/;
        } else {
            //if (isset($input['redirect'])) $redirect .= '?redirect='.$input['redirect'];
            return Redirect::to('login')
                ->with_input()
                ->with('message', 'Geçersiz kullanıcı adı ve/veya şifre.')
                ->with('message_status', 'error');
        }
    }

    public function get_logout()
    {
        Auth::logout();
        $insert = DB::table('user_auth_log')->insert(array(
           'user_id'=>Auth::user()->id,
           'timestamp'=>date('Y-m-d H:i:s'),
           'auth_type'=>'OUT'
        ));
        return Redirect::to('login')
            ->with('message', 'Çıkış yapıldı.')
            ->with('message_status', 'info');
    }
}
