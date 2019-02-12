<table class="table table-bordered table-striped cel-table">
    <thead>
    <th>id</th>
    <th>time</th>
    <th>callid</th>
    <th>queuename</th>
    <th>agent</th>
    <th>event</th>
    <th>data1</th>
    <th>data2</th>
    <th>data3</th>
    <th>data4</th>
    <th>data5</th>
    </thead>
    <tbody>

    @foreach ($queue_logs as $queue_log)

        <tr>
            <td>{{ $queue_log->id }}</td>
            <td>{{ $queue_log->time }}</td>
            <td>{{ $queue_log->callid }}</td>
            <td>{{ $queue_log->queuename }}</td>
            <td>{{ $queue_log->agent }}</td>
            <td>{{ $queue_log->event }}</td>
            <td>{{ $queue_log->data1 }}</td>
            <td>{{ $queue_log->data2 }}</td>
            <td>{{ $queue_log->data3 }}</td>
            <td>{{ $queue_log->data4 }}</td>
            <td>{{ $queue_log->data5 }}</td>
        </tr>

    @endforeach

    </tbody>
</table>