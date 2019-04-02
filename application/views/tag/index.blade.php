@section('content')

<h3>{{ $title }}</h3>

{{ Form::open(URL::current(), 'GET', array('class' => 'well form-filter clearfix')) }}
<div class="item">
    <label>Tarih - Saat Aralığı</label>
    {{ Form::text('datestart', Input::get('datestart', date('d.m.Y - 00:00')), array('class' => 'input-date datetimepicker')) }}
    {{ Form::text('dateend', Input::get('dateend', date('d.m.Y - 23:59')), array('class' => 'input-date datetimepicker')) }}
</div>

<table class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th>Temsilci</th>
            <th>Toplam Etiketlenen</th>
            <th>Toplam Etiketlenmeyen</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($agent_totals as $agent_total)
        <tr>
            <td>{{ $agent_total->agent }}</td>
            <td>{{ $agent_total->total_tagged }}</td>
            <td>{{ $agent_total->total_not_tagged }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="item">
    <label>Temsilci</label>
    {{ Form::select('agent', Cdr::get_options('agent'), Input::get('agent'), array('class' => 'input-medium')) }}
</div>
<div class="item">
    <label>Etiket</label>
    {{ Form::select('tag', Cdr::get_options('tag'), Input::get('tag'), array('class' => 'input-medium')) }}
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
    <a href="{{ Cdr::export_url() }}" class="btn"><i class="icon-download-alt"></i></a>
</div>
{{ Form::close() }}

@if (empty($query->results))
    <div class="alert alert-error">Bu kritere uygun kayıt bulunamadı.</div>
@else

    <table class="table table-bordered table-striped">
        <thead>
            @foreach ($columns as $column => $column_title)
            <th>{{ $query->sortlink($column, $column_title) }}</th>
            @endforeach
        </thead>
        <tbody>
        @foreach ($query->results as $row)
            <tr>
                @foreach ($columns as $column => $column_title)
                <td>{{ $helper->getValue($row, $column) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="50">
                    <strong>Toplam: </strong>{{ $query->total }}<br>
                    <strong>Etiketlenen: </strong>{{ $total_tagged }}<br>
                    <strong>Etiketlenmeyen: </strong>{{ $query->total - $total_tagged }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{ $query->links() }}
    <div id="per-page-container">
        {{ Form::select('per_page', $per_page_options, Input::get('per_page', 10), array('id' => 'per-page', 'class' => 'input-mini')) }}
        / sayfa
    </div>
    {{ Form::hidden('total', $query->total, array('id' => 'total')) }}
    {{ Form::hidden('page', Input::get('page', 1), array('id' => 'page')) }}
@endif

@endsection
