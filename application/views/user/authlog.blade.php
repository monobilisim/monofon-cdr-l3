@section('content')

<h3>{{$title}}</h3>
<table class="table table-bordered table-striped">
  <thead>
  <th>Kullanıcı Adı</th>
  <th>İşlem Türü</th>
  <th>İşlem Zamanı</th>
  </thead>
  <tbody>
    @foreach ($logs->results as $row)
        <tr>
            <td>{{$row->username}}</td>
            <td>{{$row->auth_type == 'IN' ? 'Giriş' : 'Çıkış'}}</td>
            <td>{{date('m-d-Y H:i:s',strtotime($row->timestamp))}}</td>
        </tr>
     @endforeach
  </tbody>

</table>

{{ $logs->links() }}

@endsection
