@section('content')

    {{ Form::open(URL::current(), 'GET', array('class' => 'well form-filter clearfix')) }}

    <a href="{{ URL::full() }}&export" class="export-xlsx"></a>

    <div class="item">
        <label>Tarih - Saat Aralığı</label>
        {{ Form::text('datestart', Input::get('datestart', date('d.m.Y - 00:00')), array('class' => 'input-date datetimepicker')) }}
        {{ Form::text('dateend', Input::get('dateend', date('d.m.Y - 23:59')), array('class' => 'input-date datetimepicker')) }}
    </div>
    <div class="item">
        <label>Durum</label>
        {{ Form::select('status', Cdr::get_options('status'), Input::get('status'), array('class' => 'input-medium')) }}
    </div>
    <div class="item">
        <label>Kapsam</label>
        {{ Form::select('scope', Cdr::get_options('scope'), Input::get('scope'), array('class' => 'input-medium')) }}
    </div>
    @if (Config::get('application.note'))
        <div class="item">
            <label>Not</label>
            {{ Form::select('note', Cdr::get_options('note'), Input::get('note'), array('class' => 'input-medium')) }}
        </div>
    @endif
    @if (Config::get('application.accountcode'))
        <div class="item">
            <label>Hesap Kodu</label>
            {{ Form::text('accountcode', Input::get('accountcode'), array('class' => 'input-small')) }}
        </div>
    @endif
    @if (Config::get('application.did'))
        <div class="item">
            <label>DID</label>
            {{ Form::select('did', Cdr::get_options('did'), Input::get('did'), array('class' => 'input-small')) }}
        </div>
    @endif
    @if (Config::get('application.call_tags'))
        <div class="item">
            <label>Etiket</label>
            {{ Form::select('tag', Cdr::get_options('tag'), Input::get('tag'), array('class' => 'input-medium')) }}
        </div>
    @endif

    <div style="clear: left"></div>

    <div class="item">
        <label>Arayan</label>
        {{ Form::text('src', Input::get('src'), array('class' => 'input-small', 'id' => 'input_src')) }}
    </div>
    <div class="item">
        <label>Arayan/Aranan</label>
        {{ Form::text('src_dst', Input::get('src_dst'), array('class' => 'input-small', 'id' => 'input_src_dst')) }}
    </div>
    <div class="item">
        <label>Aranan</label>
        {{ Form::text('dst', Input::get('dst'), array('class' => 'input-small', 'id' => 'input_dst')) }}
    </div>
    @if (Config::get('application.dstchannel'))
        <div class="item">
            <label>Aranan Kanal</label>
            {{ Form::text('dstchannel', Input::get('dstchannel'), array('class' => 'input-small')) }}
        </div>
    @endif
    <div class="item filter-tips-wrapper">
        <i class="icon-question-sign"></i>
        <div class="filter-tips">
            Kullanılabilecek arama biçimleri
            <ul>
                <li>1XX (1 ile başlayan tüm dahililer)</li>
                <li>201-205 (201 ile 205 arasındaki tüm dahililer)</li>
                <li>301,401 (301 ve 401 numaralı dahililer)</li>
            </ul>
            Ayrıca farklı aramalar ";" ile birbirine eklenebilir
        </div>
    </div>
    @if (Input::get('sort'))
        {{ Form::text('sort', Input::get('sort'), array('class' => 'hide')) }}
    @endif
    @if (Input::get('dir'))
        {{ Form::text('dir', Input::get('dir'), array('class' => 'hide')) }}
    @endif
    @if (Input::get('per_page'))
        {{ Form::text('per_page', Input::get('per_page'), array('class' => 'hide')) }}
    @endif
    <div class="item">
        {{ Form::submit('Filtrele', array('class' => 'btn btn-primary')) }}
        <a href="{{ URL::current() }}" class="btn">Sıfırla</a>
    </div>
    {{ Form::close() }}

    @if (empty($cdrs->results))
        <div class="alert alert-error">Bu kritere uygun kayıt bulunamadı.</div>
    @else

        @include('cdr.table_records')

        {{ $cdrs->links() }}

        <div id="per-page-container">
            {{ Form::select('per_page', $per_page_options, Input::get('per_page', 10), array('id' => 'per-page', 'class' => 'input-mini')) }}
            / sayfa
        </div>
        {{ Form::hidden('total', $cdrs->total, array('id' => 'total')) }}
        {{ Form::hidden('page', Input::get('page', 1), array('id' => 'page')) }}
    @endif

    <!-- Modal windows -->
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

    <div class="modal fade" id="note-modal">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">×</button>
            <h3 id="note-modal-title">Not</h3>
        </div>
        <div class="modal-body">
            <div id="note-message" class="alert" role="alert"></div>
            <div id="note-delete-confirm-message" class="alert alert-warning hide" role="alert">Bu notu silmek istediğinizden emin misiniz?</div>
            <form data-async data-target="#note-message" method="POST" class="note-form">
                <textarea id="note-textarea" class="input-block-level" name="note" rows="5" placeholder="Lütfen not yazın."></textarea>
            </form>
            <form id="note-delete-confirm-form" data-async data-target="#note-delete-confirm-message" method="POST" class="hide">
                <button form="note-delete-confirm-form" type="submit" class="btn btn-danger" id="note-delete-confirm-yes">Evet, bu notu sil</button>
                <a href="#" class="btn" id="note-delete-confirm-no">Hayır</a>
            </form>
        </div>
        <div class="modal-footer">
            <a href="#" class="btn btn-danger pull-left hide" id="note-delete-button">Sil</a>
            <a href="#" class="btn" data-dismiss="modal">İptal</a>
            <button type="submit" class="btn btn-success note-submit-button">Kaydet</button>
        </div>
    </div>

@endsection
