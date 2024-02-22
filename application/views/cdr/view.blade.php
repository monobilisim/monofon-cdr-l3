@section('content')

    <table class="table table-bordered">
        <tr>
            <th>calldate</th>
            <th>clid</th>
            <th>cnum</th>
            <th>cnam</th>
            <th>did</th>
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
            <th>recordingfile</th>
        </tr>
        <tr>
            <td>{{ $cdr->calldate }}</td>
            <td>{{ $cdr->clid }}</td>
            <td>{{ $cdr->cnum }}</td>
            <td>{{ $cdr->cnam }}</td>
            <td>{{ $cdr->did }}</td>
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
            <td>{{ $cdr->recordingfile }}</td>
        </tr>
    </table>

    @if ($note)
    <p>
        <strong>Not:</strong>
        <br>
        {{ nl2br($note) }}
    </p>
    @endif

    <div class="accordion" id="details">
        <div class="accordion-group">
            <div class="accordion-heading">
                <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-related-cdrs" href="#related-cdrs">
                    İlişkili çağrı kayıtları
                </a>
            </div>
            <div id="related-cdrs" class="accordion-body collapse">
                <div class="accordion-inner">
                    @if (empty($cdrs->results))
                        <div class="alert alert-warning">İlişkili çağrı bulunamadı.</div>
                    @else
                        @include('cdr.table_records')
                    @endif
                </div>
            </div>
        </div>
        <div class="accordion-group">
            <div class="accordion-heading">
                <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-related-cels" href="#related-cels">
                    İlişkili CEL satırları
                </a>
            </div>
            <div id="related-cels" class="accordion-body collapse">
                <div class="accordion-inner">
                    @if (empty($cels))
                        <div style="margin-top: 20px" class="alert alert-warning">İlişkili CEL satırı bulunamadı.</div>
                    @else
                        @include('cdr.table_cels')
                    @endif
                </div>
            </div>
        </div>
        <div class="accordion-group">
            <div class="accordion-heading">
                <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-related-queue-logs" href="#related-queue-logs">
                    İlişkili Queue Log satırları
                </a>
            </div>
            <div id="related-queue-logs" class="accordion-body collapse">
                <div class="accordion-inner">
                    @if (empty($queue_logs))
                        <div style="margin-top: 20px" class="alert alert-warning">İlişkili Queue Log satırı bulunamadı.</div>
                    @else
                        @include('cdr.table_queue_logs')
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
