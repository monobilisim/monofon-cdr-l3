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
	  <p class="help-block">Değiştirmek istemiyorsanız boş bırakın.</p>
    </div>
  </div>
  
  <div class="control-group{{ $errors->first('perm', ' error') }}">
    <label class="control-label">Görebileceği Dahililer</label>
    <div class="controls">
      {{ Form::text('perm', $user->perm) }}
      {{ $errors->first('perm') }}
	  <p class="help-block">
	    Kural tanımlamaları:
		<ul>
	    <li>1XX (1 ile başlayan tüm dahililer)</li>
		<li>201-205 (201 ile 205 arasındaki tüm dahililer)</li>
		<li>301,401 (301 ve 401 numaralı dahililer)</li>
		</ul>
		Ayrıca kurallar ";" ile birbirine eklenebilir
	  </p>
    </div>
  </div>

  <div class="control-group">
    <div class="controls checkbox">
    <label>
      {{ Form::checkbox('allrows', '1', $user->allrows) }} Ses kaydı olmayan satırları da görsün
    </label>
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
