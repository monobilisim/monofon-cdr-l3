<table class="table table-bordered table-striped cdr-table">
    <thead>
    <th>Tarih - Saat</th>
    @if (Config::get('application.clid'))
        <th>Arayan Tanımı</th>
    @endif
    <th>Arayan</th>
    <th>Aranan</th>
    @if (Config::get('application.dstchannel'))
        <th>Aranan Kanal</th>
    @endif
    @if (Config::get('application.accountcode'))
        <th>Hesap Kodu</th>
    @endif
    <th>Durum</th>
    <th>Süre</th>
    @if (Config::get('application.multiserver'))
        <th>Sunucu</th>
    @endif
    @if ($buttons)
        <th style="width: 75px">Ses Kaydı</th>
    @endif
    </thead>
    <tbody>


    @foreach ($cdrs->results as $cdr)

        <tr>
            <td><a class="cdr-link"
                   href="{{ URL::to('cdr/view/'.$cdr->uniqueid.'/'.strtotime($cdr->calldate)) }}">{{ date('d.m.Y', strtotime($cdr->calldate)) . ' - ' . date('H:i:s', strtotime($cdr->calldate)) }}</a>
            </td>
            @if (Config::get('application.clid'))
                <td>{{ Cdr::format_clid($cdr->clid) }}</td>
                <td>{{ $cdr->src }}</td>
            @else
                <td>{{ Cdr::format_src_dst($cdr, 'src') }}</td>
            @endif
            <td>{{ Cdr::format_src_dst($cdr, 'dst') }}</td>
            @if (Config::get('application.dstchannel'))
                <td>{{ $cdr->dstchannel }}</td>
            @endif
            @if (Config::get('application.accountcode'))
                <td>{{ $cdr->accountcode }}</td>
            @endif
            <td>{{ __("misc.$cdr->disposition") }}</td>
            <td>{{ Cdr::format_billsec($cdr->billsec) }}</td>
            @if (Config::get('application.multiserver'))
                <td>{{ $cdr->server }}</td>
            @endif
            @if ($buttons)
                <td class="buttons">@if ($cdr->$filefield AND Cdr_Controller::cdr_file_exists($cdr))
                        {{ Form::hidden('uniqueid', $cdr->uniqueid) }}
                        {{ Form::hidden('calldate', strtotime($cdr->calldate)) }}
                        <a class="btn btn-mini btn-listen" data-toggle="modal" href="#listen">Dinle</a>
                        <a class="btn btn-mini"
                           href="{{ URL::to('cdr/download/'.$cdr->uniqueid.'/'.strtotime($cdr->calldate)) }}">İndir</a>
                    @endif
                </td>
        </tr>
        @endif

    @endforeach


    </tbody>
</table>

<!-- Modal window -->
<div class="modal fade" id="listen">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h3>Ses Kaydı</h3>
    </div>
    <div class="modal-body">
        <p><span class="spinner"></span></p>
    </div>
    <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">Kapat</a>
    </div>
</div>