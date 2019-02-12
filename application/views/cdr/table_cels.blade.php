<table class="table table-bordered table-striped cel-table">
    <thead>
    <th>id</th>
    <th>eventtype</th>
    <th>eventtime</th>
    <th>cid_name</th>
    <th>cid_num</th>
    <th>cid_ani</th>
    <th>cid_rdnis</th>
    <th>cid_dnid</th>
    <th>exten</th>
    <th>context</th>
    <th>channame</th>
    <th>appname</th>
    <th>appdata</th>
    <th>amaflags</th>
    <th>accountcode</th>
    <th>uniqueid</th>
    <th>linkedid</th>
    <th>peer</th>
    <th>userdeftype</th>
    <th>extra</th>
    </thead>
    <tbody>

    @foreach ($cels as $cel)

        <tr>
            <td>{{ $cel->id }}</td>
            <td>{{ $cel->eventtype }}</td>
            <td>{{ $cel->eventtime }}</td>
            <td>{{ $cel->cid_name }}</td>
            <td>{{ $cel->cid_num }}</td>
            <td>{{ $cel->cid_ani }}</td>
            <td>{{ $cel->cid_rdnis }}</td>
            <td>{{ $cel->cid_dnid }}</td>
            <td>{{ $cel->exten }}</td>
            <td>{{ $cel->context }}</td>
            <td>{{ $cel->channame }}</td>
            <td>{{ $cel->appname }}</td>
            <td>{{ $cel->appdata }}</td>
            <td>{{ $cel->amaflags }}</td>
            <td>{{ $cel->accountcode }}</td>
            <td>{{ $cel->uniqueid }}</td>
            <td>{{ $cel->linkedid }}</td>
            <td>{{ $cel->peer }}</td>
            <td>{{ $cel->userdeftype }}</td>
            <td>{{ $cel->extra }}</td>
        </tr>

    @endforeach

    </tbody>
</table>