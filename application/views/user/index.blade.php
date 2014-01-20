@section('content')

<h3>{{ $title }}</h3>
<p><a href="/user/create">Yeni Kullanıcı Ekle</a></p>

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
        <a href="/user/update/{{ $user->id }}">Güncelle</a>
	@if (Auth::user()->id !== $user->id)
	|
        <a data-toggle="modal" href="#delete_{{ $user->id }}">Sil</a>
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
