@section('content')

  {{ Form::open(URL::current(), 'POST', array('class' => 'form-horizontal')) }}

  <div class="control-group{{ $errors->first('username', ' error') }}">
    <label class="control-label">Kullanıcı Adı</label>
    <div class="controls">
      {{ Form::text('username', $user->username) }}
      <p class="help-inline">{{ $errors->first('username') }}</p>
    </div>
  </div>

  <div class="control-group{{ $errors->first('password', ' error') }}">
    <label class="control-label">Şifre</label>
    <div class="controls">
      {{ Form::password('password') }}
      <p class="help-inline">{{ $errors->first('password') }}</p>
	  <p class="help-block">Değiştirmek istemiyorsanız boş bırakın.</p>
    </div>
  </div>
  
  <div class="control-group{{ $errors->first('perm', ' error') }}">
    <label class="control-label">Görebileceği Dahililer</label>
    <div class="controls">
      {{ Form::text('perm', $user->perm) }}
      <p class="help-inline">{{ $errors->first('perm') }}</p>
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
    {{ Form::hidden('allrows', '0') }}
    <label>
      {{ Form::checkbox('allrows', '1', $user->allrows) }} Ses kaydı olmayan satırları da görsün
    </label>
    </div>
  </div>
  
  <div class="control-group">
    <div class="controls checkbox">
    {{ Form::hidden('buttons', '0') }}
    <label>
      {{ Form::checkbox('buttons', '1', $user->buttons) }} Dinle/İndir butonlarını görsün
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
