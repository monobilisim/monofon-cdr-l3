@section('content') 
 
<h3>{{$title}}</h3>   
<table class="table table-bordered table-striped">
  <thead> 
  <th>Kullanıcı Adı</th>
  <th>İşlem Türü</th>
  <th>İşlem Zamanı</th>
  </thead>
  <tbody>
    @foreach ($logs->results as $key)
        <tr> 
            <td>{{$key->username}}</td>
            <td>{{$key->auth_type}}</td>
            <td>{{date('m-d-Y H:i:s',strtotime($key->timestamp))}}</td>
        </tr> 
     @endforeach
  </tbody>
  
</table> 
<div style="width: 100%">   
  <div style="margin-left: 35%;"><?php echo $logs->links(); ?>  </div> 
</div> 
@endsection
