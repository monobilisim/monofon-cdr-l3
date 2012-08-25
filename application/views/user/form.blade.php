@section('content')

  {{ Form::open(URL::current(), 'POST', array('class' => 'form-horizontal')) }}

  <div class="control-group{{ $errors->first('username', ' error') }}">
    <label class="control-label">Kullanıcı Adı</label>
    <div class="controls">
      {{ Form::text('username', $user->username) }}
      {{ $errors->first('username') }}
    </div>
  </div>

  <div class="control-group{{ $errors->first('password', ' error') }}">
    <label class="control-label">Şifre</label>
    <div class="controls">
      {{ Form::password('password') }}
      {{ $errors->first('password') }}
	  <p class="help-block">Değiştirmek istemiyorsanız boş bırakınız.</p>
    </div>
  </div>
  
  <div class="control-group{{ $errors->first('perm', ' error') }}">
    <label class="control-label">İzinli Dahili</label>
    <div class="controls">
      {{ Form::text('perm', $user->perm) }}
      {{ $errors->first('perm') }}
    </div>
  </div>

  <div class="control-group">
    <label class="control-label">Rol</label>
    <div class="controls">
      {{ Form::select('role', $roles, $user->role) }}
    </div>
  </div>
  
  <div class="form-actions">
    {{ Form::submit('Kaydet', array('class' => 'btn btn-primary')) }}</p>
  </div>
  
  {{ Form::close() }}

@endsection