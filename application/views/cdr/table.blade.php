<table class="table table-bordered table-striped cdr-table">
    <thead>
    <th>{{ $cdrs->sortlink('calldate', 'Tarih - Saat') }}</th>
    @if (Config::get('application.did'))
        <th>{{ $cdrs->sortlink('did', 'DID') }}</th>
    @endif
    @if (Config::get('application.clid'))
        <th>{{ $cdrs->sortlink('clid', 'Arayan Tanımı') }}</th>
    @endif
    <th>{{ $cdrs->sortlink('src', 'Arayan') }}</th>
    <th>{{ $cdrs->sortlink('dst', 'Aranan') }}</th>
    @if (Config::get('application.dstchannel'))
        <th>{{ $cdrs->sortlink('dstchannel', 'Aranan Kanal') }}</th>
    @endif
    @if (Config::get('application.accountcode'))
        <th>{{ $cdrs->sortlink('server', 'Hesap Kodu') }}</th>
    @endif
    <th>{{ $cdrs->sortlink('disposition', 'Durum') }}</th>
    <th>{{ $cdrs->sortlink('billsec', 'Süre') }}</th>
    @if ($display_agent_billsec)
        <th>Temsilci Süre</th>
    @endif
    @if ($buttons_download || $buttons_listen)
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
            @if ($display_agent_billsec)
                @if ($cdr->billsec <= $cdr->agent_billsec)
                    {{ $cdr->agent_billsec = null }}
                @endif
                <td>{{ Cdr::format_agent_billsec($cdr->agent_billsec) }}</td>
            @endif
            @if ($buttons_download || $buttons_listen)
      		<td class="buttons">
        	    @if ($cdr->$filefield)
          	    {{ Form::hidden('uniqueid', $cdr->uniqueid) }}
          	    {{ Form::hidden('calldate', strtotime($cdr->calldate)) }}
          	    @if ($buttons_listen)
            	      <a class="btn btn-mini btn-listen" data-toggle="modal" href="#listen">Dinle</a>
	            @endif
          	    @if ($buttons_download)
            	      <a class="btn btn-mini" href="{{ URL::to('cdr/download/'.$cdr->uniqueid.'/'.strtotime($cdr->calldate)) }}">İndir</a>
          	    @endif

                    @endif
                </td>
        </tr>
        @endif

    @endforeach

    <tr>
        <td colspan="{{ $colspan + 1 }}">
            <strong>Toplam arama sayısı: </strong>{{ $cdrs->total }}<br>
            <strong>Toplam arama süresi: </strong>{{ Cdr::format_billsec($total_billsec) }}
        </td>
    </tr>

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
