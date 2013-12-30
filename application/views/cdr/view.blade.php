@section('content')

<table class="table table-bordered">
<tr>
<th>calldate</th>
<th>clid</th>
<th>src</th>
<th>dst</th>
<th>dcontext</th>
<th>channel</th>
<th>dstchannel</th>
<th>lastapp</th>
<th>lastdata</th>
<th>duration</th>
<th>billsec</th>
<th>disposition</th>
<th>uniqueid</th>
<th>userfield</th>
</tr>
<tr>
<td>{{ $cdr->calldate }}</td>
<td>{{ $cdr->clid }}</td>
<td>{{ $cdr->src }}</td>
<td>{{ $cdr->dst }}</td>
<td>{{ $cdr->dcontext }}</td>
<td>{{ $cdr->channel }}</td>
<td>{{ $cdr->dstchannel }}</td>
<td>{{ $cdr->lastapp }}</td>
<td>{{ $cdr->lastdata }}</td>
<td>{{ $cdr->duration }}</td>
<td>{{ $cdr->billsec }}</td>
<td>{{ $cdr->disposition }}</td>
<td>{{ $cdr->uniqueid }}</td>
<td>{{ $cdr->userfield }}</td>
</tr>
</table>

@endsection

