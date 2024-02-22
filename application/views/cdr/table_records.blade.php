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
        @if (Config::get('application.call_tags'))
        <th>{{ $cdrs->sortlink('tag', 'Etiket') }}</th>
        @endif
        <th>{{ $cdrs->sortlink('disposition', 'Durum') }}</th>
        <th>{{ $cdrs->sortlink('billsec', 'Süre') }}</th>
        @if ($display_agent_billsec)
        <th>Temsilci Süre</th>
        @endif
        @if ($buttons_download || $buttons_listen)
        <th style="width: 75px">Ses Kaydı</th>
        @endif
        @if (Config::get('application.note'))
        <th>Not</th>
        @endif
    </thead>
    <tbody>


        @foreach ($cdrs->results as $cdr)

        <tr>
            <td><a class="cdr-link" href="{{ URL::to('cdr/view/'.$cdr->uniqueid.'/'.strtotime($cdr->calldate)) }}">{{ date('d.m.Y - H:i:s', strtotime($cdr->calldate)) }}</a>
            </td>
            @if (Config::get('application.did'))
            <td>{{ $cdr->did }}</td>
            @endif
            @if (Config::get('application.clid'))
            @if (Cdr::format_clid($cdr->clid) !== '')
            <td>{{ Cdr::format_clid($cdr->clid) }}</td>
            @else
            <td>{{ $cdr->cnam }} ({{ $cdr->cnum }})</td>
            @endif
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
            @if (Config::get('application.call_tags'))
            <td>
                <form method="post" action="{{ URL::to('cdr/update') }}">
                    <input type="hidden" name="linkedid" value="{{ $cdr->linkedid }}">
                    {{ Form::select('tag', Cdr::get_options('tag_update'), Cdr::format_tag($cdr->tag), array('class' => 'input-medium')) }}
                    <button type="submit" class="btn btn-mini">Güncelle</button>
                </form>
            </td>
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
                @if (Config::get('application.note'))
                    <?php
                    $note = $cdr->note;
                    $note_add_button_class = ($note) ? ' hide' : '';
                    $note_edit_button_class = ($note) ? '' : ' hide';
                    $uniqueid_class = str_replace('.', '_', $cdr->uniqueid);
                    ?>
                    <td class="note_{{ $uniqueid_class }}">
                    {{ Form::hidden('uniqueid', $cdr->uniqueid) }}
                    {{ Form::hidden('cdr_info', $cdr->src . ' → ' . $cdr->dst . ' (' . date('d.m.Y - H:i:s', strtotime($cdr->calldate)) . ')') }}
                        <a class="btn btn-mini btn-note note-edit-button{{ $note_edit_button_class }}" data-toggle="modal" href="#note-modal">Düzenle</a>
                        <a class="btn btn-mini btn-note note-add-button{{ $note_add_button_class }}" data-toggle="modal" href="#note-modal">Ekle</a>
                    </td>
                @endif
            @endif
        </tr>


        @endforeach

        <tr>
            <td colspan="10">
                <strong>Toplam arama sayısı: </strong>{{ $cdrs->total }}<br>
                <strong>Toplam arama süresi: </strong>{{ Cdr::format_billsec($total_billsec) }}
            </td>
        </tr>

    </tbody>
</table>
