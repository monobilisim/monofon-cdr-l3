@section('content')

<h3>{{ $title }}</h3>

<div class="row">
{{ Form::open(URL::current(), 'GET', array('id' => 'user_filter', 'class' => 'well span5 clearfix')) }}
  <div class="span3">
    <label><strong>Kullanıcı adı</strong></label>
    {{ Form::text('username', Input::get('username')) }}
  </div>
  <div class="span1">
    {{ Form::submit('Filtrele', array('class' => 'btn', 'style' => 'margin-top: 23px')) }}
  </div>
{{ Form::close() }}

<p class="pull-right">
  <a class="btn btn-primary" href="/user/create">Kullanıcı Ekle</a>
</p>

</div>

@if (empty($users->results))
  <div class="alert alert-error">Kullanıcı kaydı bulunamadı.</div>
@else
<table class="table table-bordered table-striped">
  <thead>
  <th>{{ $users->sortlink('username', 'Kullanıcı Adı') }}</th>
  <th>{{ $users->sortlink('perm', 'Görebileceği Dahililer') }}</th>
  <th>Rol</th>
  <th>İşlemler</th>
  </thead>
  <tbody>
  @foreach ($users->results as $user)
    <tr>
      <td>{{ $user->username }}</td>
      <td>{{ $user->perm }}</td>
      <td>{{ $roles[$user->role] }}</td>
      <td>
		<a class="btn" href="/user/update/{{ $user->id }}">
		  <i class="icon-edit icon-black"></i>
		  Güncelle
		</a>
        @if (Auth::user()->id !== $user->id)
		<a data-toggle="modal" class="btn" href="#delete_{{ $user->id }}">
		  <i class="icon-trash icon-black"></i>
		  Sil
		</a>
		@endif
        {{ View::make('delete', array('id' => $user->id, 'identifier' => 'Kullanıcı [' . $user->username . ']', 'delete_link' => '/user/delete/' . $user->id)) }}
      </td>
    </tr>
  @endforeach
  </tbody>
</table>

{{ $users->links() }}

@endif

@endsection