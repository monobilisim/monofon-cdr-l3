@section('content')

<h3>{{$title}}</h3>   
<table class="table table-bordered table-striped">
  <thead> 
  <th>Kullanıcı Adı</th>
  <th>İşlem Türü</th>
  <th>İşlem Zamanı</th>
  </thead>
  <tbody>
    @foreach ($logs as $key)
        <tr> 
            <td>{{$key->username}}</td>
            <td>{{$key->auth_type}}</td>
            <td>{{date('m-d-Y H:i:s',strtotime($key->timestamp))}}</td>
        </tr>
     @endforeach
  </tbody>
</table> 

 

@endsection
