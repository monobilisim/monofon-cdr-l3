@section('content')
  
  @if ( ! Auth::user())
  
  {{ Form::open('login') }}
  
  @if (Session::has('login_error'))
    <div class="alert alert-error">Geçersiz kullanıcı adı ve/veya şifre</div>
  @endif
  
  @if (Session::has('logged_out'))
    <div class="alert alert-info">Çıkış yapıldı</div>
  @endif
  
  <p>{{ Form::label('username', 'Kullanıcı Adı') }}</p>
  <p>{{ Form::text('username', Input::old('username'), array('required' => 'required')) }}</p>
  
  <p>{{ Form::label('password', 'Şifre') }}</p>
  <p>{{ Form::password('password') }}</p>

  <p style="margin-bottom: 14px"><label class="checkbox">{{ Form::checkbox('remember', 1, false) }}Beni hatırla</label></p>
  
  @if (Input::get('redirect'))
  {{ Form::hidden('redirect', Input::get('redirect')) }}
  @endif
  
  <p>{{ Form::submit('Giriş', array('class' => 'btn btn-primary')) }}</p>
  
  {{ Form::close() }}
  
  @endif

@endsection